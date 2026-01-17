<?php
/**
 * Elementor Site Kit + JetEngine/JetSmartFilters Exporter
 *
 * Exporte :
 * - Active Kit Elementor (Site Settings) : globals colors/typos, layout, breakpoints, settings complets
 * - Options Elementor importantes (viewport, container width, etc.)
 * - CSS générée par Elementor (global.css + post-KIT.css) avec hash + extrait
 * - JetEngine : options/config (meta boxes, CPT, tax, relations si présents) + listings/templates existants
 * - JetSmartFilters : filtres (CPT) + meta/settings
 *
 * USAGE:
 * - Copier dans wp-content/mu-plugins/
 * - Se connecter en admin
 * - Aller sur: https://ton-site.com/?elementor_export_site_kit=1
 * - Télécharger le JSON
 *
 * IMPORTANT: supprimer ce fichier après usage (sécurité).
 */

if (!defined('ABSPATH')) exit;

add_action('wp_loaded', function () {
    if (!isset($_GET['elementor_export_site_kit'])) return;

    if (!is_user_logged_in() || !current_user_can('manage_options')) {
        wp_die('Accès refusé');
    }

    // Elementor n'est pas obligatoire pour exporter Jet*, mais on veut le kit si dispo.
    $elementor_loaded = did_action('elementor/loaded') && class_exists('\Elementor\Plugin');

    $out = [
        'meta' => [
            'generated_at' => date('c'),
            'site_url' => home_url('/'),
            'wp_version' => get_bloginfo('version'),
            'php_version' => PHP_VERSION,
            'elementor_loaded' => (bool) $elementor_loaded,
            'elementor_version' => defined('ELEMENTOR_VERSION') ? ELEMENTOR_VERSION : null,
            'elementor_pro_version' => defined('ELEMENTOR_PRO_VERSION') ? ELEMENTOR_PRO_VERSION : null,
        ],
        'elementor' => [
            'active_kit' => null,
            'options' => [],
            'global_css' => [],
        ],
        'jetengine' => [
            'plugin_detected' => (bool) class_exists('\Jet_Engine'),
            'options_dump' => [],
            'post_types_detected' => [],
            'listings_templates' => [],
        ],
        'jetsmartfilters' => [
            'plugin_detected' => (bool) (class_exists('\Jet_Smart_Filters') || class_exists('\JetSmartFilters')),
            'post_types_detected' => [],
            'filters' => [],
        ],
        'wp' => [
            'theme' => [
                'stylesheet' => get_stylesheet(),
                'template' => get_template(),
                'name' => wp_get_theme()->get('Name'),
                'version' => wp_get_theme()->get('Version'),
            ],
            'active_plugins' => [],
        ],
    ];

    // Plugins actifs (utile à Claude pour comprendre le contexte)
    $active_plugins = (array) get_option('active_plugins', []);
    $out['wp']['active_plugins'] = array_values($active_plugins);

    // -------------------------
    // ELEMENTOR : KIT + OPTIONS
    // -------------------------
    if ($elementor_loaded) {
        $plugin = \Elementor\Plugin::instance();

        // Active kit id (la source de vérité)
        $kit_id = null;
        try {
            if (isset($plugin->kits_manager) && method_exists($plugin->kits_manager, 'get_active_id')) {
                $kit_id = (int) $plugin->kits_manager->get_active_id();
            }
        } catch (\Throwable $e) {}

        $kit_payload = [
            'kit_id' => $kit_id ?: null,
            'kit_post' => null,
            'kit_settings' => null,
            'kit_page_settings_meta' => null,
            'globals_extracted' => [
                'system_colors' => null,
                'custom_colors' => null,
                'system_typography' => null,
                'custom_typography' => null,
            ],
        ];

        if ($kit_id) {
            $kit_post = get_post($kit_id);
            if ($kit_post) {
                $kit_payload['kit_post'] = [
                    'ID' => $kit_post->ID,
                    'post_title' => $kit_post->post_title,
                    'post_type' => $kit_post->post_type,
                    'post_status' => $kit_post->post_status,
                ];
            }

            // Settings via Document (le plus fiable)
            try {
                if (isset($plugin->documents) && method_exists($plugin->documents, 'get')) {
                    $doc = $plugin->documents->get($kit_id);
                    if ($doc && method_exists($doc, 'get_settings')) {
                        $kit_payload['kit_settings'] = $doc->get_settings();
                    }
                }
            } catch (\Throwable $e) {
                $kit_payload['kit_settings_error'] = $e->getMessage();
            }

            // Meta brute (utile quand certains settings ne remontent pas)
            $meta = get_post_meta($kit_id, '_elementor_page_settings', true);
            $kit_payload['kit_page_settings_meta'] = normalize_maybe_serialized($meta);

            // Extraire globals “classiques” si présents dans settings/meta
            $globals_keys = ['system_colors', 'custom_colors', 'system_typography', 'custom_typography'];
            foreach ($globals_keys as $k) {
                $kit_payload['globals_extracted'][$k] = extract_global_from_sources($k, $kit_payload['kit_settings'], $kit_payload['kit_page_settings_meta']);
            }
        }

        $out['elementor']['active_kit'] = $kit_payload;

        // Options Elementor utiles (varie selon versions → on dump un set large)
        $elementor_option_keys = [
            'elementor_container_width',
            'elementor_viewport_lg',
            'elementor_viewport_md',
            'elementor_default_generic_fonts',
            'elementor_disable_color_schemes',
            'elementor_disable_typography_schemes',
            'elementor_cpt_support',
            'elementor_space_between_widgets',
            'elementor_global_image_lightbox',
            'elementor_load_fa4_shim',
            'elementor_experiment-e_dom_optimization',
            'elementor_experiment-container',
            'elementor_experiment-optimized_assets_loading',
            'elementor_experiment-additional_custom_breakpoints',
            'elementor_custom_breakpoints',
        ];

        $opts = [];
        foreach ($elementor_option_keys as $key) {
            $val = get_option($key, null);
            if ($val !== null) {
                $opts[$key] = normalize_maybe_serialized($val);
            }
        }
        $out['elementor']['options'] = $opts;

        // CSS générée Elementor (global.css, post-KIT.css)
        $out['elementor']['global_css'] = collect_elementor_css_files($kit_id);
    }

    // -------------------------
    // JETENGINE
    // -------------------------
    $out['jetengine']['options_dump'] = collect_options_by_prefixes([
        'jet_engine',
        'jet-engine',
        'jet_engine_',
        'jet_engine-',
        'jet_engine_meta',
        'jet_engine_rel',
    ]);

    // Détecter post types Jet* existants
    $jet_post_types_candidates = [
        'jet-engine',
        'jet-engine-cpt',
        'jet-engine-tax',
        'jet-engine-meta',
        'jet-engine-listing',
        'jet-engine-template',
        'jet_engine',
        'jet-engine-booking',
        'jet-engine-relations',
        'jet-engine-query',
        'jet-engine-profile',
        'jet-engine-forms',
        'jet-engine-macros',
        'jet-engine-glossaries',
        'jet-engine-options-pages',
    ];

    $jet_post_types = [];
    foreach ($jet_post_types_candidates as $pt) {
        if (post_type_exists($pt)) $jet_post_types[] = $pt;
    }
    $out['jetengine']['post_types_detected'] = $jet_post_types;

    // Collecter “listings/templates” JetEngine (si CPT trouvés) + meta Elementor si présents
    $out['jetengine']['listings_templates'] = collect_posts_by_types($jet_post_types, [
        '_elementor_data',
        '_elementor_page_settings',
        '_elementor_template_type',
        '_elementor_edit_mode',
        '_jet_engine_listing',
        '_listing_type',
        '_jet_engine_listing_settings',
    ]);

    // -------------------------
    // JETSMARTFILTERS
    // -------------------------
    $jsf_candidates = [
        'jet-smart-filters',
        'jet-smart-filters-filter',
        'jet_smart_filters',
        'jet-smart-filters',
        'jet-smart-filters-presets',
    ];

    $jsf_post_types = [];
    foreach ($jsf_candidates as $pt) {
        if (post_type_exists($pt)) $jsf_post_types[] = $pt;
    }
    $out['jetsmartfilters']['post_types_detected'] = $jsf_post_types;

    // Si on a un post type “filters”, récupérer les filtres + meta
    $out['jetsmartfilters']['filters'] = collect_posts_by_types($jsf_post_types, [
        '_jet_smart_filters_settings',
        '_filter_settings',
        '_elementor_data',
        '_elementor_page_settings',
    ]);

    // -------------------------
    // OUTPUT JSON
    // -------------------------
    $filename = 'elementor-site-kit-export-' . date('Y-m-d') . '.json';

    nocache_headers();
    header('Content-Type: application/json; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    echo json_encode($out, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}, 20);

/**
 * Helpers
 */

function normalize_maybe_serialized($value) {
    // Si c'est déjà un array/object (ex: maybe_unserialize a déjà converti), on retourne direct
    if (is_array($value) || is_object($value)) {
        return $value;
    }

    // Unserialize si WP a stocké en serialized (string)
    if (is_string($value)) {
        $maybe = maybe_unserialize($value);
        if ($maybe !== $value) {
            // Peut devenir array/object ici
            $value = $maybe;
            if (is_array($value) || is_object($value)) {
                return $value;
            }
        }

        // Tenter JSON decode si string JSON
        $trim = trim($value);
        if ($trim !== '' && ($trim[0] === '{' || $trim[0] === '[')) {
            $json = json_decode($trim, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                return $json;
            }
        }
    }

    return $value;
}

function extract_global_from_sources($key, $settings, $meta) {
    if (is_array($settings) && array_key_exists($key, $settings)) return $settings[$key];
    if (is_array($meta) && array_key_exists($key, $meta)) return $meta[$key];
    // parfois c'est nested dans "global_*" selon versions
    if (is_array($settings)) {
        foreach ($settings as $k => $v) {
            if ($k === $key) return $v;
            if (is_array($v) && array_key_exists($key, $v)) return $v[$key];
        }
    }
    if (is_array($meta)) {
        foreach ($meta as $k => $v) {
            if ($k === $key) return $v;
            if (is_array($v) && array_key_exists($key, $v)) return $v[$key];
        }
    }
    return null;
}

function collect_elementor_css_files($kit_id = null) {
    $upload = wp_upload_dir();
    $base_dir = trailingslashit($upload['basedir']);
    $base_url = trailingslashit($upload['baseurl']);

    $candidates = [];

    // global.css
    $global_path = $base_dir . 'elementor/css/global.css';
    $candidates[] = [
        'label' => 'elementor_global_css',
        'path' => $global_path,
        'url'  => $base_url . 'elementor/css/global.css',
    ];

    // kit css (post-{id}.css)
    if ($kit_id) {
        $kit_path = $base_dir . 'elementor/css/post-' . intval($kit_id) . '.css';
        $candidates[] = [
            'label' => 'elementor_kit_css',
            'path' => $kit_path,
            'url'  => $base_url . 'elementor/css/post-' . intval($kit_id) . '.css',
        ];
    }

    // résultat avec hash + excerpt
    $files = [];
    foreach ($candidates as $c) {
        $entry = $c;
        $entry['exists'] = file_exists($c['path']);
        if ($entry['exists']) {
            $content = @file_get_contents($c['path']);
            if ($content !== false) {
                $entry['size_bytes'] = strlen($content);
                $entry['sha1'] = sha1($content);
                // excerpt (pour éviter JSON énorme)
                $entry['excerpt_first_20000_chars'] = substr($content, 0, 20000);
            } else {
                $entry['read_error'] = 'Unable to read file';
            }
            $entry['mtime'] = @filemtime($c['path']) ?: null;
        }
        $files[] = $entry;
    }
    return $files;
}

function collect_options_by_prefixes(array $prefixes) {
    global $wpdb;

    // On va chercher les options correspondant aux prefixes (best effort)
    // NB: on limite à 500 pour éviter le délire.
    $like_parts = [];
    foreach ($prefixes as $p) {
        $p = esc_sql($p);
        $like_parts[] = "option_name LIKE '{$p}%'";
    }
    if (!$like_parts) return [];

    $where = implode(' OR ', $like_parts);
    $sql = "SELECT option_name, option_value FROM {$wpdb->options} WHERE ($where) LIMIT 500";
    $rows = $wpdb->get_results($sql, ARRAY_A);

    $dump = [];
    foreach ($rows as $r) {
        $dump[$r['option_name']] = normalize_maybe_serialized($r['option_value']);
    }
    ksort($dump);
    return $dump;
}

function collect_posts_by_types(array $post_types, array $meta_keys) {
    if (empty($post_types)) return [];

    $items = [];

    foreach ($post_types as $pt) {
        $q = new WP_Query([
            'post_type' => $pt,
            'post_status' => 'any',
            'posts_per_page' => 200,
            'orderby' => 'ID',
            'order' => 'ASC',
            'no_found_rows' => true,
        ]);

        $posts_out = [];
        foreach ($q->posts as $p) {
            $meta_out = [];
            foreach ($meta_keys as $mk) {
                $val = get_post_meta($p->ID, $mk, true);
                if ($val !== '' && $val !== null) {
                    $meta_out[$mk] = normalize_maybe_serialized($val);
                }
            }

            $posts_out[] = [
                'ID' => $p->ID,
                'post_title' => $p->post_title,
                'post_name' => $p->post_name,
                'post_status' => $p->post_status,
                'edit_link' => get_edit_post_link($p->ID, ''),
                'meta' => $meta_out,
            ];
        }

        $items[$pt] = [
            'count' => count($posts_out),
            'posts' => $posts_out,
        ];
    }

    return $items;
}
<?php
/**
 * Elementor Schema Extractor
 *
 * Ce script extrait automatiquement la structure de tous les widgets Elementor
 * et génère une documentation JSON complète.
 *
 * USAGE:
 * - Copie ce fichier dans wp-content/mu-plugins/
 * - Connecte-toi en admin
 * - Va sur : https://ton-site.com/?elementor_extract_schema=1
 * - Le navigateur télécharge un JSON
 *
 * IMPORTANT: Supprime ce fichier après utilisation (sécurité)
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Bootloader MU-plugin safe:
 * - Attend que WP + plugins soient chargés
 * - Puis exécute si le paramètre est présent
 */
add_action('wp_loaded', function () {
    if (!isset($_GET['elementor_extract_schema'])) {
        return;
    }

    // Sécurité
    if (!is_user_logged_in() || !current_user_can('manage_options')) {
        wp_die('Accès refusé');
    }

    // Vérifier qu'Elementor est bien chargé
    if (!did_action('elementor/loaded') || !class_exists('\Elementor\Plugin')) {
        wp_die("Elementor n'est pas chargé (plugin inactif ou chargement incomplet).");
    }

    // Générer
    extract_elementor_schema();
}, 20);

function extract_elementor_schema() {
    $widgets_manager = \Elementor\Plugin::instance()->widgets_manager;
    $widgets = $widgets_manager->get_widget_types();

    $schema = [
        'generated_at' => date('Y-m-d H:i:s'),
        'elementor_version' => defined('ELEMENTOR_VERSION') ? ELEMENTOR_VERSION : null,
        'widgets' => [],
        'controls_reference' => [],
        'dynamic_tags' => [],
        'container_structure' => [],
    ];

    // Extraire chaque widget
    foreach ($widgets as $widget_name => $widget) {
        $widget_data = extract_widget_schema($widget);
        $schema['widgets'][$widget_name] = $widget_data;
    }

    // Extraire les types de contrôles disponibles
    $schema['controls_reference'] = extract_controls_reference();

    // Extraire les dynamic tags disponibles
    $schema['dynamic_tags'] = extract_dynamic_tags();

    // Extraire la structure des containers
    $schema['container_structure'] = extract_container_structure();

    // Output JSON (download)
    nocache_headers();
    header('Content-Type: application/json; charset=utf-8');
    header('Content-Disposition: attachment; filename="elementor-schema.json"');
    echo json_encode($schema, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function extract_widget_schema($widget) {
    $widget_data = [
        'name' => $widget->get_name(),
        'title' => $widget->get_title(),
        'icon' => $widget->get_icon(),
        'categories' => $widget->get_categories(),
        'keywords' => $widget->get_keywords(),
        'controls' => [],
        'content_controls' => [],
        'style_controls' => [],
        'advanced_controls' => [],
    ];

    try {
        $controls_stack = $widget->get_controls();

        foreach ($controls_stack as $control_id => $control) {
            $control_data = [
                'id' => $control_id,
                'type' => $control['type'] ?? 'unknown',
                'label' => $control['label'] ?? '',
                'default' => $control['default'] ?? null,
                'section' => $control['section'] ?? '',
                'tab' => $control['tab'] ?? '',
                'selectors' => $control['selectors'] ?? [],
                'options' => $control['options'] ?? [],
                'condition' => $control['condition'] ?? [],
                'responsive' => isset($control['responsive']) ? true : false,
            ];

            // Classifier par tab
            $tab = $control['tab'] ?? 'content';
            if (strpos($tab, 'style') !== false) {
                $widget_data['style_controls'][$control_id] = $control_data;
            } elseif (strpos($tab, 'advanced') !== false) {
                $widget_data['advanced_controls'][$control_id] = $control_data;
            } else {
                $widget_data['content_controls'][$control_id] = $control_data;
            }

            $widget_data['controls'][$control_id] = $control_data;
        }
    } catch (\Throwable $e) {
        $widget_data['error'] = $e->getMessage();
    }

    return $widget_data;
}

function extract_controls_reference() {
    $controls_manager = \Elementor\Plugin::instance()->controls_manager;
    $control_types = $controls_manager->get_controls();

    $reference = [];
    foreach ($control_types as $type => $control) {
        $reference[$type] = [
            'type' => $type,
            'class' => is_object($control) ? get_class($control) : null,
        ];
    }

    return $reference;
}

function extract_dynamic_tags() {
    $dynamic_tags = [];

    try {
        $plugin = \Elementor\Plugin::instance();
        $dynamic_tags_manager = $plugin->dynamic_tags ?? null;

        if ($dynamic_tags_manager) {
            $tags = $dynamic_tags_manager->get_tags();

            foreach ($tags as $tag_name => $tag_class) {
                try {
                    $tag_instance = new $tag_class();
                    $dynamic_tags[$tag_name] = [
                        'name' => $tag_instance->get_name(),
                        'title' => $tag_instance->get_title(),
                        'group' => method_exists($tag_instance, 'get_group') ? $tag_instance->get_group() : null,
                        'categories' => method_exists($tag_instance, 'get_categories') ? $tag_instance->get_categories() : null,
                    ];
                } catch (\Throwable $e) {
                    $dynamic_tags[$tag_name] = ['error' => $e->getMessage()];
                }
            }
        }
    } catch (\Throwable $e) {
        $dynamic_tags['_error'] = $e->getMessage();
    }

    // Info ACF (si Elementor Pro)
    if (class_exists('\ElementorPro\Modules\DynamicTags\ACF\Module')) {
        $dynamic_tags['_acf_info'] = 'ACF Dynamic Tags are available (Elementor Pro)';
    }

    return $dynamic_tags;
}

function extract_container_structure() {
    return [
        'container' => [
            'elType' => 'container',
            'required_fields' => ['id', 'elType', 'settings', 'elements'],
            'common_settings' => [
                'content_width' => ['boxed', 'full'],
                'flex_direction' => ['row', 'column'],
                'flex_justify_content' => ['flex-start', 'center', 'flex-end', 'space-between', 'space-around', 'space-evenly'],
                'flex_align_items' => ['flex-start', 'center', 'flex-end', 'stretch'],
                'flex_gap' => ['unit' => 'px', 'size' => 10],
                'padding' => ['unit' => 'px', 'top' => '', 'right' => '', 'bottom' => '', 'left' => '', 'isLinked' => true],
                'margin' => ['unit' => 'px', 'top' => '', 'right' => '', 'bottom' => '', 'left' => '', 'isLinked' => true],
            ],
            'responsive_suffixes' => ['', '_tablet', '_mobile'],
        ],
        'widget' => [
            'elType' => 'widget',
            'required_fields' => ['id', 'elType', 'widgetType', 'settings', 'elements'],
        ],
        'dynamic_tag_format' => [
            'example' => [
                '__dynamic__' => [
                    'field_name' => '[elementor-tag id="xxx" name="acf-text" settings="%7B%22key%22%3A%22field_name%22%7D"]'
                ]
            ],
            'settings_encoded' => 'URL encoded JSON with field key',
        ]
    ];
}
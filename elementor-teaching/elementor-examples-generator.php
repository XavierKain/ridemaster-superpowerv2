<?php
/**
 * Elementor JSON Examples Generator
 *
 * Génère des exemples JSON pour chaque widget Elementor
 * avec différentes configurations (basique, complet, dynamique)
 *
 * USAGE:
 * - Copie dans wp-content/mu-plugins/
 * - Connecte-toi en admin
 * - Va sur : https://ton-site.com/?elementor_generate_examples=1
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
    if (!isset($_GET['elementor_generate_examples'])) {
        return;
    }

    // Sécurité
    if (!is_user_logged_in() || !current_user_can('manage_options')) {
        wp_die('Accès refusé');
    }

    // Elementor chargé ?
    if (!did_action('elementor/loaded') || !class_exists('\Elementor\Plugin')) {
        wp_die("Elementor n'est pas chargé (plugin inactif ou chargement incomplet).");
    }

    generate_elementor_examples();
}, 20);

function generate_elementor_examples() {
    $output = [
        'meta' => [
            'generated_at' => date('Y-m-d H:i:s'),
            'elementor_version' => defined('ELEMENTOR_VERSION') ? ELEMENTOR_VERSION : null,
            'purpose' => 'Documentation pour génération IA de templates Elementor',
        ],
        'structure_reference' => get_structure_reference(),
        'widgets_examples' => [],
        'dynamic_tags_examples' => get_dynamic_tags_examples(),
        'common_patterns' => get_common_patterns(),
        'acf_integration' => get_acf_examples(),
        'loop_builder' => get_loop_builder_examples(),
    ];

    // Widgets prioritaires
    $priority_widgets = [
        'heading', 'text-editor', 'image', 'button', 'icon', 'divider',
        'image-box', 'icon-box', 'star-rating', 'image-gallery',
        'video', 'google_maps', 'icon-list', 'counter', 'progress',
        'testimonial', 'tabs', 'accordion', 'toggle', 'social-icons',
        'alert', 'html', 'shortcode', 'menu-anchor', 'spacer',
        // Pro widgets
        'posts', 'portfolio', 'gallery', 'form', 'slides', 'nav-menu',
        'animated-headline', 'price-list', 'price-table', 'flip-box',
        'call-to-action', 'media-carousel', 'testimonial-carousel',
        'loop-grid', 'loop-carousel',
    ];

    $widgets_manager = \Elementor\Plugin::instance()->widgets_manager;

    foreach ($priority_widgets as $widget_name) {
        $widget = $widgets_manager->get_widget_types($widget_name);
        if ($widget) {
            $output['widgets_examples'][$widget_name] = generate_widget_examples($widget);
        } else {
            // Garder une trace si un widget n'existe pas sur cette install
            $output['widgets_examples'][$widget_name] = [
                '_missing' => true,
                'reason' => 'Widget non trouvé (Elementor Free/Pro, modules désactivés, etc.)',
            ];
        }
    }

    // Output JSON (download)
    nocache_headers();
    header('Content-Type: application/json; charset=utf-8');
    header('Content-Disposition: attachment; filename="elementor-examples-' . date('Y-m-d') . '.json"');
    echo json_encode($output, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function get_structure_reference() {
    return [
        'page_template' => [
            'title' => 'Template Title',
            'type' => 'page|single|archive|header|footer|loop-item',
            'version' => '0.4',
            'page_settings' => [],
            'content' => [],
        ],
        'container' => [
            'id' => '8_caracteres_hex',
            'elType' => 'container',
            'isInner' => false,
            'settings' => [
                'content_width' => 'boxed|full',
                'flex_direction' => 'row|column',
                'flex_justify_content' => 'flex-start|center|flex-end|space-between|space-around|space-evenly',
                'flex_align_items' => 'flex-start|center|flex-end|stretch',
                'flex_wrap' => 'nowrap|wrap',
                'flex_gap' => ['column' => '10', 'row' => '10', 'unit' => 'px'],
                'padding' => ['top' => '20', 'right' => '20', 'bottom' => '20', 'left' => '20', 'unit' => 'px', 'isLinked' => true],
                'margin' => ['top' => '0', 'right' => '0', 'bottom' => '0', 'left' => '0', 'unit' => 'px', 'isLinked' => true],
                'background_background' => 'classic|gradient',
                'background_color' => '#FFFFFF',
                'border_radius' => ['top' => '0', 'right' => '0', 'bottom' => '0', 'left' => '0', 'unit' => 'px', 'isLinked' => true],
                'box_shadow_box_shadow' => ['horizontal' => 0, 'vertical' => 0, 'blur' => 10, 'spread' => 0, 'color' => 'rgba(0,0,0,0.5)'],
            ],
            'elements' => [],
        ],
        'widget_base' => [
            'id' => '8_caracteres_hex',
            'elType' => 'widget',
            'widgetType' => 'widget-name',
            'isInner' => false,
            'settings' => [],
            'elements' => [],
        ],
        'responsive_suffixes' => [
            'desktop' => '(no suffix)',
            'tablet' => '_tablet',
            'mobile' => '_mobile',
            'example' => 'padding_tablet, flex_direction_mobile',
        ],
        'id_generation' => 'Générer 8 caractères hex aléatoires: substr(md5(uniqid()), 0, 8)',
    ];
}

function generate_widget_examples($widget) {
    $controls = $widget->get_controls();

    return [
        'widget_info' => [
            'name' => $widget->get_name(),
            'title' => $widget->get_title(),
            'categories' => $widget->get_categories(),
        ],
        'minimal' => generate_minimal_example($widget),
        'complete' => generate_complete_example($widget, $controls),
        'with_dynamic_tags' => generate_dynamic_example($widget, $controls),
        'all_controls' => extract_all_controls($controls),
    ];
}

function generate_minimal_example($widget) {
    return [
        'id' => generate_element_id(),
        'elType' => 'widget',
        'widgetType' => $widget->get_name(),
        'isInner' => false,
        'settings' => [],
        'elements' => [],
    ];
}

function generate_complete_example($widget, $controls) {
    $settings = [];

    foreach ($controls as $id => $control) {
        // Skip contrôles système
        if (strpos($id, '_') === 0) {
            continue;
        }

        $default = $control['default'] ?? null;
        if ($default !== null && $default !== '' && $default !== []) {
            $settings[$id] = $default;
        }
    }

    return [
        'id' => generate_element_id(),
        'elType' => 'widget',
        'widgetType' => $widget->get_name(),
        'isInner' => false,
        'settings' => $settings,
        'elements' => [],
    ];
}

function generate_dynamic_example($widget, $controls) {
    $settings = [];
    $dynamic = [];

    // Champs typiques compatibles dynamic tags (heuristique)
    $dynamic_capable = ['title', 'description', 'text', 'content', 'link', 'image', 'url', 'editor'];

    foreach ($controls as $id => $control) {
        if (in_array($id, $dynamic_capable, true)) {
            $dynamic[$id] = '[elementor-tag id="' . generate_element_id() . '" name="acf-text" settings="%7B%22key%22%3A%22field_' . $id . '%22%7D"]';
        }
    }

    if (!empty($dynamic)) {
        $settings['__dynamic__'] = $dynamic;
    }

    return [
        'id' => generate_element_id(),
        'elType' => 'widget',
        'widgetType' => $widget->get_name(),
        'isInner' => false,
        'settings' => $settings,
        'elements' => [],
    ];
}

function extract_all_controls($controls) {
    $organized = [
        'content' => [],
        'style' => [],
        'advanced' => [],
    ];

    foreach ($controls as $id => $control) {
        // Skip contrôles internes (sauf exceptions)
        if (strpos($id, '_') === 0 && !in_array($id, ['_padding', '_margin', '_element_width'], true)) {
            continue;
        }

        $tab = $control['tab'] ?? 'content';
        $section = $control['section'] ?? 'general';

        $control_info = [
            'type' => $control['type'] ?? 'unknown',
            'label' => $control['label'] ?? $id,
            'default' => $control['default'] ?? null,
            'options' => $control['options'] ?? null,
            'selectors' => !empty($control['selectors']) ? '(has CSS selectors)' : null,
            'responsive' => isset($control['responsive']) || strpos($id, '_tablet') !== false || strpos($id, '_mobile') !== false,
        ];

        // Nettoyer les null
        $control_info = array_filter($control_info, static function ($v) {
            return $v !== null;
        });

        $category = 'content';
        if (strpos($tab, 'style') !== false) {
            $category = 'style';
        } elseif (strpos($tab, 'advanced') !== false) {
            $category = 'advanced';
        }

        if (!isset($organized[$category][$section])) {
            $organized[$category][$section] = [];
        }

        $organized[$category][$section][$id] = $control_info;
    }

    return $organized;
}

function get_dynamic_tags_examples() {
    return [
        'acf_text' => [
            'format' => '[elementor-tag id="UNIQUE_ID" name="acf-text" settings="ENCODED_JSON"]',
            'settings_decoded' => ['key' => 'your_acf_field_name'],
            'usage' => [
                'title' => [
                    '__dynamic__' => [
                        'title' => '[elementor-tag id="abc12345" name="acf-text" settings="%7B%22key%22%3A%22camp_title%22%7D"]',
                    ],
                ],
            ],
        ],
        'acf_image' => [
            'format' => '[elementor-tag id="UNIQUE_ID" name="acf-image" settings="ENCODED_JSON"]',
            'settings_decoded' => ['key' => 'your_acf_image_field'],
            'usage' => [
                'image' => [
                    '__dynamic__' => [
                        'image' => '[elementor-tag id="def67890" name="acf-image" settings="%7B%22key%22%3A%22camp_photo%22%7D"]',
                    ],
                ],
            ],
        ],
        'acf_url' => [
            'format' => '[elementor-tag id="UNIQUE_ID" name="acf-url" settings="ENCODED_JSON"]',
            'settings_decoded' => ['key' => 'your_acf_url_field'],
        ],
        'post_title' => [
            'format' => '[elementor-tag id="UNIQUE_ID" name="post-title" settings="%7B%7D"]',
            'no_settings_needed' => true,
        ],
        'post_excerpt' => [
            'format' => '[elementor-tag id="UNIQUE_ID" name="post-excerpt" settings="%7B%7D"]',
        ],
        'featured_image' => [
            'format' => '[elementor-tag id="UNIQUE_ID" name="post-featured-image" settings="%7B%7D"]',
        ],
        'post_url' => [
            'format' => '[elementor-tag id="UNIQUE_ID" name="post-url" settings="%7B%7D"]',
        ],
        'encoding_reference' => [
            'original' => '{"key":"field_name"}',
            'encoded' => '%7B%22key%22%3A%22field_name%22%7D',
            'php_function' => 'rawurlencode(json_encode($settings))',
        ],
    ];
}

function get_common_patterns() {
    return [
        'typography' => [
            'typography_typography' => 'custom',
            'typography_font_family' => 'Roboto',
            'typography_font_size' => ['unit' => 'px', 'size' => 16],
            'typography_font_weight' => '400|500|600|700',
            'typography_line_height' => ['unit' => 'em', 'size' => 1.5],
            'typography_letter_spacing' => ['unit' => 'px', 'size' => 0],
        ],
        'spacing' => [
            'padding' => ['top' => '20', 'right' => '20', 'bottom' => '20', 'left' => '20', 'unit' => 'px', 'isLinked' => true],
            'margin' => ['top' => '0', 'right' => '0', 'bottom' => '0', 'left' => '0', 'unit' => 'px', 'isLinked' => false],
        ],
        'border' => [
            'border_border' => 'solid|dashed|dotted|double|none',
            'border_width' => ['top' => '1', 'right' => '1', 'bottom' => '1', 'left' => '1', 'unit' => 'px', 'isLinked' => true],
            'border_color' => '#000000',
            'border_radius' => ['top' => '0', 'right' => '0', 'bottom' => '0', 'left' => '0', 'unit' => 'px', 'isLinked' => true],
        ],
        'background' => [
            'background_background' => 'classic|gradient',
            'background_color' => '#FFFFFF',
            'background_image' => ['url' => '', 'id' => ''],
            'background_position' => 'center center',
            'background_size' => 'cover|contain|auto',
        ],
        'box_shadow' => [
            'box_shadow_box_shadow_type' => 'yes',
            'box_shadow_box_shadow' => [
                'horizontal' => 0,
                'vertical' => 4,
                'blur' => 10,
                'spread' => 0,
                'color' => 'rgba(0,0,0,0.1)',
            ],
        ],
        'hover_animation' => [
            '_hover_animation' => 'grow|shrink|pulse|float|bounce|none',
        ],
    ];
}

function get_acf_examples() {
    $examples = [
        'text_field' => [
            '__dynamic__' => [
                'title' => '[elementor-tag id="' . generate_element_id() . '" name="acf-text" settings="%7B%22key%22%3A%22your_text_field%22%7D"]',
            ],
        ],
        'image_field' => [
            '__dynamic__' => [
                'image' => '[elementor-tag id="' . generate_element_id() . '" name="acf-image" settings="%7B%22key%22%3A%22your_image_field%22%7D"]',
            ],
        ],
        'url_field' => [
            '__dynamic__' => [
                'link' => '[elementor-tag id="' . generate_element_id() . '" name="acf-url" settings="%7B%22key%22%3A%22your_url_field%22%7D"]',
            ],
        ],
        'number_field' => [
            '__dynamic__' => [
                'counter_number' => '[elementor-tag id="' . generate_element_id() . '" name="acf-number" settings="%7B%22key%22%3A%22your_number_field%22%7D"]',
            ],
        ],
        'relationship_note' => 'Les champs relationship/post_object nécessitent souvent un loop ou du code custom',
    ];

    // Si ACF est actif, lister les groupes/champs
    if (function_exists('acf_get_field_groups')) {
        $field_groups = acf_get_field_groups();
        $examples['available_field_groups'] = [];

        foreach ($field_groups as $group) {
            $fields = function_exists('acf_get_fields') ? acf_get_fields($group['key']) : null;
            $field_list = [];

            if ($fields) {
                foreach ($fields as $field) {
                    $field_list[$field['name']] = [
                        'type' => $field['type'],
                        'key' => $field['key'],
                        'label' => $field['label'],
                    ];
                }
            }

            $examples['available_field_groups'][$group['title']] = [
                'key' => $group['key'],
                'location' => $group['location'],
                'fields' => $field_list,
            ];
        }
    }

    return $examples;
}

function get_loop_builder_examples() {
    return [
        'loop_item_template' => [
            'title' => 'Camp Card Loop Item',
            'type' => 'loop-item',
            'version' => '0.4',
            'page_settings' => [],
            'content' => [
                [
                    'id' => generate_element_id(),
                    'elType' => 'container',
                    'isInner' => false,
                    'settings' => [
                        'flex_direction' => 'column',
                        'padding' => ['top' => '20', 'right' => '20', 'bottom' => '20', 'left' => '20', 'unit' => 'px', 'isLinked' => true],
                        'background_background' => 'classic',
                        'background_color' => '#FFFFFF',
                        'border_radius' => ['top' => '8', 'right' => '8', 'bottom' => '8', 'left' => '8', 'unit' => 'px', 'isLinked' => true],
                    ],
                    'elements' => [
                        [
                            'id' => generate_element_id(),
                            'elType' => 'widget',
                            'widgetType' => 'image',
                            'settings' => [
                                '__dynamic__' => [
                                    'image' => '[elementor-tag id="xxx" name="post-featured-image" settings="%7B%7D"]',
                                ],
                            ],
                            'elements' => [],
                        ],
                        [
                            'id' => generate_element_id(),
                            'elType' => 'widget',
                            'widgetType' => 'heading',
                            'settings' => [
                                'header_size' => 'h3',
                                '__dynamic__' => [
                                    'title' => '[elementor-tag id="xxx" name="post-title" settings="%7B%7D"]',
                                ],
                            ],
                            'elements' => [],
                        ],
                        [
                            'id' => generate_element_id(),
                            'elType' => 'widget',
                            'widgetType' => 'text-editor',
                            'settings' => [
                                '__dynamic__' => [
                                    'editor' => '[elementor-tag id="xxx" name="post-excerpt" settings="%7B%7D"]',
                                ],
                            ],
                            'elements' => [],
                        ],
                    ],
                ],
            ],
        ],
        'loop_grid_usage' => [
            'id' => generate_element_id(),
            'elType' => 'widget',
            'widgetType' => 'loop-grid',
            'settings' => [
                'template_id' => 'ID_DU_LOOP_ITEM_TEMPLATE',
                'posts_per_page' => 6,
                'columns' => 3,
                'columns_tablet' => 2,
                'columns_mobile' => 1,
                'row_gap' => ['unit' => 'px', 'size' => 20],
                'column_gap' => ['unit' => 'px', 'size' => 20],
                'query_post_type' => 'camp', // ton CPT
                'query_orderby' => 'date',
                'query_order' => 'desc',
            ],
            'elements' => [],
        ],
    ];
}

function generate_element_id() {
    return substr(md5(uniqid(mt_rand(), true)), 0, 8);
}
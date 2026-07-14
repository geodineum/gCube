<?php
declare(strict_types=1);
/**
 * Customizer: Navigation Sections
 *
 * Navigation button styling and content mapping. Custom size/border
 * controls only appear for the 'custom' and 'classic' presets — the
 * other presets override them.
 *
 * @package    gCube
 * @subpackage Customizer\Sections
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Register navigation styling settings
 *
 * @param WP_Customize_Manager $wp_customize Customizer manager instance
 * @return void
 */
function gcube_register_navigation_styling(WP_Customize_Manager $wp_customize): void {
    // Button style preset
    $wp_customize->add_setting('nav_button_style', [
        'default' => gcube_default('nav_button_style'),
        'sanitize_callback' => 'gtemplate_sanitize_option',
        'transport' => 'refresh',
    ]);
    $wp_customize->add_control('nav_button_style', [
        'label' => __('Button Style Preset', 'gcube'),
        'description' => __('Choose a pre-designed button style', 'gcube'),
        'section' => 'nav_button_styling',
        'type' => 'select',
        'priority' => 1,
        'choices' => [
            'sleek' => __('Sleek - Modern minimal', 'gcube'),
            'glass' => __('Glass - Frosted effect', 'gcube'),
            'pill' => __('Pill - Rounded capsule', 'gcube'),
            'outline' => __('Outline - Transparent with border', 'gcube'),
            'neon' => __('Neon - Glowing effect', 'gcube'),
            'classic' => __('Classic - Original style', 'gcube'),
            'custom' => __('Custom - Use settings below', 'gcube'),
        ],
    ]);

    $preset_overridable = static function (): bool {
        return in_array(gcube_mod('nav_button_style'), ['custom', 'classic'], true);
    };

    // Button colors (feed CSS vars used by the pill/outline presets and
    // the custom/classic styles)
    $nav_colors = [
        'nav_button_bg_color' => __('Button Background', 'gcube'),
        'nav_button_text_color' => __('Button Text', 'gcube'),
        'nav_button_hover_bg_color' => __('Button Hover Background', 'gcube'),
        'nav_button_hover_text_color' => __('Button Hover Text', 'gcube'),
        'nav_button_border_color' => __('Button Border', 'gcube'),
    ];
    $priority = 5;
    foreach ($nav_colors as $id => $label) {
        $wp_customize->add_setting($id, [
            'default' => gcube_default($id),
            'sanitize_callback' => 'gtemplate_sanitize_hex_color',
            'transport' => 'refresh',
        ]);
        $wp_customize->add_control(new WP_Customize_Color_Control($wp_customize, $id, [
            'label' => $label,
            'section' => 'nav_button_styling',
            'priority' => $priority++,
        ]));
    }

    // Size and dimension settings: [label, css_value-sanitized?, preset-dependent?]
    $nav_settings = [
        'nav_button_padding' => [__('Button Padding', 'gcube'), true, true],
        'nav_button_font_size' => [__('Button Font Size', 'gcube'), true, true],
        'nav_button_border_width' => [__('Button Border Width', 'gcube'), true, true],
        'nav_button_border_radius' => [__('Button Border Radius', 'gcube'), true, true],
        'nav_button_margin' => [__('Button Margin', 'gcube'), true, false],
        'nav_wrapper_width' => [__('Nav Wrapper Width', 'gcube'), true, false],
        'nav_button_min_width' => [__('Button Min Width', 'gcube'), true, false],
        'nav_button_max_height' => [__('Button Max Height', 'gcube'), true, false],
    ];

    foreach ($nav_settings as $id => [$label, $is_css_value, $preset_dependent]) {
        $wp_customize->add_setting($id, [
            'default' => gcube_default($id),
            'sanitize_callback' => $is_css_value ? 'gtemplate_sanitize_css_value' : 'sanitize_text_field',
            'transport' => 'refresh',
        ]);
        $control_args = [
            'label' => $label,
            'section' => 'nav_button_styling',
            'type' => 'text',
        ];
        if ($preset_dependent) {
            $control_args['active_callback'] = $preset_overridable;
        }
        $wp_customize->add_control($id, $control_args);
    }

    // Border style (keyword, not a dimension)
    $wp_customize->add_setting('nav_button_border_style', [
        'default' => gcube_default('nav_button_border_style'),
        'sanitize_callback' => 'gtemplate_sanitize_option',
        'transport' => 'refresh',
    ]);
    $wp_customize->add_control('nav_button_border_style', [
        'label' => __('Button Border Style', 'gcube'),
        'section' => 'nav_button_styling',
        'type' => 'select',
        'active_callback' => $preset_overridable,
        'choices' => [
            'solid' => __('Solid', 'gcube'),
            'dashed' => __('Dashed', 'gcube'),
            'dotted' => __('Dotted', 'gcube'),
            'double' => __('Double', 'gcube'),
            'none' => __('None', 'gcube'),
        ],
    ]);
}

/**
 * Register navigation button content mapping
 *
 * @param WP_Customize_Manager $wp_customize Customizer manager instance
 * @return void
 */
function gcube_register_navigation_buttons(WP_Customize_Manager $wp_customize): void {
    $face_choices = [
        '0' => __('Top', 'gcube'),
        '1' => __('Front (Default)', 'gcube'),
        '2' => __('Right', 'gcube'),
        '3' => __('Back', 'gcube'),
        '4' => __('Left', 'gcube'),
        '5' => __('Bottom', 'gcube'),
    ];

    for ($i = 1; $i <= 6; $i++) {
        // Button enabled
        $wp_customize->add_setting("nav_button_{$i}_enabled", [
            'default' => gcube_default("nav_button_{$i}_enabled"),
            'sanitize_callback' => 'absint',
            'transport' => 'refresh',
        ]);
        $wp_customize->add_control("nav_button_{$i}_enabled", [
            'label' => sprintf(__('Button %d — Enable', 'gcube'), $i),
            'description' => __('Enable this navigation button', 'gcube'),
            'section' => 'nav_button_content',
            'type' => 'checkbox',
        ]);

        // Button label
        $wp_customize->add_setting("nav_button_{$i}_label", [
            'default' => gcube_default("nav_button_{$i}_label"),
            'sanitize_callback' => 'sanitize_text_field',
            'transport' => 'refresh',
        ]);
        $wp_customize->add_control("nav_button_{$i}_label", [
            'label' => sprintf(__('Button %d - Label', 'gcube'), $i),
            'section' => 'nav_button_content',
            'type' => 'text',
        ]);

        // Target face
        $wp_customize->add_setting("nav_button_{$i}_target_face", [
            'default' => gcube_default("nav_button_{$i}_target_face"),
            'sanitize_callback' => 'absint',
            'transport' => 'refresh',
        ]);
        $wp_customize->add_control("nav_button_{$i}_target_face", [
            'label' => sprintf(__('Button %d - Target Face', 'gcube'), $i),
            'section' => 'nav_button_content',
            'type' => 'select',
            'choices' => $face_choices,
        ]);

        // Template override
        $wp_customize->add_setting("nav_button_{$i}_template", [
            'default' => gcube_default("nav_button_{$i}_template"),
            'sanitize_callback' => 'sanitize_text_field',
            'transport' => 'refresh',
        ]);
        $wp_customize->add_control("nav_button_{$i}_template", [
            'label' => sprintf(__('Button %d - Template Override', 'gcube'), $i),
            'description' => __('Template ID (empty = use face default)', 'gcube'),
            'section' => 'nav_button_content',
            'type' => 'text',
        ]);

        // Content slug
        $wp_customize->add_setting("nav_button_{$i}_slug", [
            'default' => gcube_default("nav_button_{$i}_slug"),
            'sanitize_callback' => 'sanitize_title',
            'transport' => 'refresh',
        ]);
        $wp_customize->add_control("nav_button_{$i}_slug", [
            'label' => sprintf(__('Button %d - Page/Post Slug', 'gcube'), $i),
            'section' => 'nav_button_content',
            'type' => 'text',
        ]);
    }
}

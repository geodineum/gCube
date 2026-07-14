<?php
declare(strict_types=1);
/**
 * Customizer: Cube Colors
 *
 * Cube-specific colors registered into the shared 'colors' section
 * (owned by parent gTemplate). The site palette, gradient cycle,
 * scrollbar, and typography colors are parent-registered; navigation
 * button colors live in Navigation Styling.
 *
 * @package    gCube
 * @subpackage Customizer\Sections
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Register cube-specific color settings and controls
 *
 * @param WP_Customize_Manager $wp_customize Customizer manager instance
 * @return void
 */
function gcube_register_colors_settings(WP_Customize_Manager $wp_customize): void {
    $color_labels = [
        'color_border' => __('Border Color', 'gcube'),
        'color_highlight' => __('Highlight Color', 'gcube'),
        'color_hover' => __('Hover Color', 'gcube'),
        'color_background_button' => __('Button Background', 'gcube'),
        'color_text_button' => __('Button Text', 'gcube'),
    ];

    $priority = 60;
    foreach ($color_labels as $id => $label) {
        $wp_customize->add_setting($id, [
            'default' => gcube_default($id),
            'transport' => 'refresh',
            'sanitize_callback' => 'gtemplate_sanitize_hex_color',
        ]);
        $wp_customize->add_control(new WP_Customize_Color_Control($wp_customize, $id, [
            'label' => $label,
            'section' => 'colors',
            'priority' => $priority++,
        ]));
    }
}

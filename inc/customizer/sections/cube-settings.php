<?php
declare(strict_types=1);
/**
 * Customizer: Cube Sizing Section
 *
 * Cube dimensions, perspective, and glass surface. The face-4 backside
 * image lives in the Face 4 section.
 *
 * @package    gCube
 * @subpackage Customizer\Sections
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Register cube settings and controls
 *
 * @param WP_Customize_Manager $wp_customize Customizer manager instance
 * @return void
 */
function gcube_register_cube_settings(WP_Customize_Manager $wp_customize): void {
    // Dimension settings
    $cube_settings = [
        'perspective_scene' => __('Perspective for Scene', 'gcube'),
        'perspective_origin_scene' => __('Perspective Origin for Scene', 'gcube'),
        'default_cubeheight' => __('The Height of the Cube', 'gcube'),
        'default_cubewidth' => __('The Width of the Cube', 'gcube'),
    ];

    foreach ($cube_settings as $id => $label) {
        $wp_customize->add_setting($id, [
            'default' => gcube_default($id),
            'transport' => 'refresh',
            'sanitize_callback' => 'gtemplate_sanitize_css_value',
        ]);
        $wp_customize->add_control($id, [
            'label' => $label,
            'section' => 'cube_settings',
            'type' => 'text',
        ]);
    }

    // Glass-face surface color (rgba — also used by the front face in glass mode)
    $wp_customize->add_setting('semi_transparant', [
        'default' => gcube_default('semi_transparant'),
        'transport' => 'refresh',
        'sanitize_callback' => 'gtemplate_sanitize_css_color',
    ]);
    $wp_customize->add_control('semi_transparant', [
        'label' => __('Glass Surface Color', 'gcube'),
        'description' => __('CSS color for glass faces, e.g. rgba(255, 255, 255, 0.28)', 'gcube'),
        'section' => 'cube_settings',
        'type' => 'text',
    ]);
}

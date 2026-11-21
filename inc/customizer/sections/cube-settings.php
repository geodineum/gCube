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

    $wp_customize->add_setting('cube_splat_url', [
        'default' => gcube_default('cube_splat_url'),
        'transport' => 'refresh',
        'sanitize_callback' => 'esc_url_raw',
    ]);
    $wp_customize->add_control('cube_splat_url', [
        'label' => __('Glass Box Splat', 'gcube'),
        'description' => __('URL of a Gaussian splat (.spz, .ply, .splat, .ksplat) shown inside the cube while a glass face is in front. Empty: off.', 'gcube'),
        'section' => 'cube_settings',
        'type' => 'url',
    ]);

    $wp_customize->add_setting('cube_splat_flip', [
        'default' => gcube_default('cube_splat_flip'),
        'transport' => 'refresh',
        'sanitize_callback' => 'absint',
    ]);
    $wp_customize->add_control('cube_splat_flip', [
        'label' => __('Splat is captured upside down', 'gcube'),
        'description' => __('Most captures are; untick if the object shows upside down.', 'gcube'),
        'section' => 'cube_settings',
        'type' => 'checkbox',
    ]);

    $wp_customize->add_setting('cube_splat_spin', [
        'default' => gcube_default('cube_splat_spin'),
        'transport' => 'refresh',
        'sanitize_callback' => 'absint',
    ]);
    $wp_customize->add_control('cube_splat_spin', [
        'label' => __('Splat turn time (seconds)', 'gcube'),
        'description' => __('One full turn of the object; 0 holds it still.', 'gcube'),
        'section' => 'cube_settings',
        'type' => 'number',
    ]);

    $wp_customize->add_setting('cube_splat_credit', [
        'default' => gcube_default('cube_splat_credit'),
        'transport' => 'refresh',
        'sanitize_callback' => 'sanitize_text_field',
    ]);
    $wp_customize->add_control('cube_splat_credit', [
        'label' => __('Splat credit', 'gcube'),
        'description' => __('Shown under the object, e.g. "Cinnamon Star Cookie by Dany Bittel, CC BY 4.0". Needed for CC BY captures.', 'gcube'),
        'section' => 'cube_settings',
        'type' => 'text',
    ]);

    $wp_customize->add_setting('cube_splat_credit_url', [
        'default' => gcube_default('cube_splat_credit_url'),
        'transport' => 'refresh',
        'sanitize_callback' => 'esc_url_raw',
    ]);
    $wp_customize->add_control('cube_splat_credit_url', [
        'label' => __('Splat credit link', 'gcube'),
        'section' => 'cube_settings',
        'type' => 'url',
    ]);
}

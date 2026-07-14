<?php
declare(strict_types=1);
/**
 * Customizer: PWA Section
 *
 * Progressive Web App configuration settings.
 *
 * @package    gCube
 * @subpackage Customizer\Sections
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Register PWA settings and controls
 *
 * @param WP_Customize_Manager $wp_customize Customizer manager instance
 * @return void
 */
function gcube_register_pwa_settings(WP_Customize_Manager $wp_customize): void {
    // PWA is a Chapter-2 capability — hide the toggle/controls until the extension is present
    if (function_exists('gcube_manifest_available') && !gcube_manifest_available()) {
        return;
    }

    // Enable PWA
    $wp_customize->add_setting('enable_pwa', [
        'default' => gcube_default('enable_pwa'),
        'sanitize_callback' => 'absint',
    ]);
    $wp_customize->add_control('enable_pwa', [
        'label' => __('Enable PWA Functionality', 'gcube'),
        'section' => 'gcube_pwa',
        'type' => 'checkbox',
    ]);

    $pwa_active = static function (): bool {
        return (bool) gcube_mod('enable_pwa');
    };

    // PWA Icon 192x192
    $wp_customize->add_setting('pwa_icon_192', [
        'default' => gcube_default('pwa_icon_192'),
        'sanitize_callback' => 'esc_url_raw',
    ]);
    $wp_customize->add_control(new WP_Customize_Image_Control($wp_customize, 'pwa_icon_192', [
        'label' => __('PWA Icon (192x192)', 'gcube'),
        'section' => 'gcube_pwa',
        'active_callback' => $pwa_active,
    ]));

    // PWA Icon 512x512
    $wp_customize->add_setting('pwa_icon_512', [
        'default' => gcube_default('pwa_icon_512'),
        'sanitize_callback' => 'esc_url_raw',
    ]);
    $wp_customize->add_control(new WP_Customize_Image_Control($wp_customize, 'pwa_icon_512', [
        'label' => __('PWA Icon (512x512)', 'gcube'),
        'section' => 'gcube_pwa',
        'active_callback' => $pwa_active,
    ]));

    // PWA Short Name
    $wp_customize->add_setting('pwa_short_name', [
        'default' => gcube_default('pwa_short_name'),
        'sanitize_callback' => 'sanitize_text_field',
    ]);
    $wp_customize->add_control('pwa_short_name', [
        'label' => __('PWA Short Name', 'gcube'),
        'description' => __('Empty = derived from the site name.', 'gcube'),
        'section' => 'gcube_pwa',
        'type' => 'text',
        'active_callback' => $pwa_active,
    ]);

    // PWA Background Color
    $wp_customize->add_setting('pwa_background_color', [
        'default' => gcube_default('pwa_background_color'),
        'sanitize_callback' => 'gtemplate_sanitize_hex_color',
    ]);
    $wp_customize->add_control(new WP_Customize_Color_Control($wp_customize, 'pwa_background_color', [
        'label' => __('PWA Background Color', 'gcube'),
        'section' => 'gcube_pwa',
        'active_callback' => $pwa_active,
    ]));

    // PWA Theme Color
    $wp_customize->add_setting('pwa_theme_color', [
        'default' => gcube_default('pwa_theme_color'),
        'sanitize_callback' => 'gtemplate_sanitize_hex_color',
    ]);
    $wp_customize->add_control(new WP_Customize_Color_Control($wp_customize, 'pwa_theme_color', [
        'label' => __('PWA Theme Color', 'gcube'),
        'section' => 'gcube_pwa',
        'active_callback' => $pwa_active,
    ]));

    // PWA Install Banner
    $wp_customize->add_setting('pwa_install_banner', [
        'default' => gcube_default('pwa_install_banner'),
        'sanitize_callback' => 'esc_url_raw',
    ]);
    $wp_customize->add_control(new WP_Customize_Image_Control($wp_customize, 'pwa_install_banner', [
        'label' => __('PWA Install Banner Image', 'gcube'),
        'description' => __('Upload an image for the install banner.', 'gcube'),
        'section' => 'gcube_pwa',
        'active_callback' => $pwa_active,
    ]));
}

<?php
declare(strict_types=1);
/**
 * gCube Settings Schema — single source of truth
 *
 * Canonical setting IDs and defaults for every gCube theme mod.
 * Customizer registration, CSS output, content sync, PWA manifest,
 * and script settings all resolve through gcube_mod()/gcube_face_mod()
 * so names and defaults can never diverge per consumer.
 *
 * Face settings are keyed 1-6 (customizer numbering). DOM face ids are
 * 0-5; gcube_face_mod() owns that translation.
 *
 * @package    gCube
 * @subpackage Customizer
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Canonical defaults for all gCube settings.
 *
 * @return array<string, mixed> setting_id => default
 */
function gcube_customizer_defaults(): array {
    static $defaults = null;
    if ($defaults !== null) {
        return $defaults;
    }

    $defaults = [
        // Colors — cube-specific only. The site palette (color_background,
        // color_text, color_header, scrollbar_*) is parent-owned (gTemplate
        // registers and renders it); grad_color1-4 are parent-registered but
        // listed here because gCube's surface computation consumes them
        // (values match the parent's).
        'gcube_bg_style'             => 'gradient_animated',
        'grad_color1'                => '#ee7752',
        'grad_color2'                => '#e73c7e',
        'grad_color3'                => '#23a6d5',
        'grad_color4'                => '#23d5ab',
        'color_border'               => '#F5F9E9',
        'color_highlight'            => '#e51022',
        'color_hover'                => '#c40e1d',
        'color_background_button'    => '#F5F9E9',
        'color_text_button'          => '#F5F9E9',
        'nav_button_bg_color'        => '#ffffff',
        'nav_button_text_color'      => '#000000',
        'nav_button_hover_bg_color'  => '#dddddd',
        'nav_button_hover_text_color' => '#000000',
        'nav_button_border_color'    => '#000000',

        // Cube geometry
        'perspective_scene'          => '200vmin',
        'perspective_origin_scene'   => '50% 50%',
        'default_cubeheight'         => '80vmin',
        'default_cubewidth'          => '80vmin',
        'semi_transparant'           => 'rgba(255, 255, 255, 0.28)',
        'cube_four_bg_image'         => '',
        'cube_four_bg_size'          => 'cover',

        // Navigation styling
        'nav_button_style'           => 'sleek',
        'nav_button_padding'         => '10px 20px',
        'nav_button_margin'          => '10px',
        'nav_button_font_size'       => '16px',
        'nav_button_border_style'    => 'solid',
        'nav_button_border_width'    => '1px',
        'nav_button_border_radius'   => '20%',
        'nav_wrapper_width'          => '15%',
        'nav_button_min_width'       => '18vmin',
        'nav_button_max_height'      => '5vmin',

        // PWA
        'enable_pwa'                 => 0,
        'pwa_icon_192'               => '',
        'pwa_icon_512'               => '',
        'pwa_short_name'             => '',  // empty = derived from the site name
        'pwa_background_color'       => '#1a1a1a',
        'pwa_theme_color'            => '#e51022',
        'pwa_install_banner'         => '',
    ];

    $button_labels = [1 => 'Top', 2 => 'Home', 3 => 'Right', 4 => 'Back', 5 => 'Left', 6 => 'Bottom'];
    $grad_defaults = ['#ee7752', '#e73c7e', '#23a6d5', '#23d5ab'];

    for ($i = 1; $i <= 6; $i++) {
        // Navigation buttons
        $defaults["nav_button_{$i}_enabled"]     = true;
        $defaults["nav_button_{$i}_label"]       = $button_labels[$i];
        $defaults["nav_button_{$i}_target_face"] = $i - 1;
        $defaults["nav_button_{$i}_template"]    = '';
        $defaults["nav_button_{$i}_slug"]        = '';

        // Face content
        $defaults["cube_face_{$i}_enabled"]         = true;
        $defaults["cube_face_{$i}_source"]          = 'demo';
        $defaults["cube_face_{$i}_category_filter"] = '';
        $defaults["cube_face_{$i}_posts_per_page"]  = 10;
        $defaults["cube_face_{$i}_content_id"]      = 0;
        $defaults["cube_face_{$i}_custom_html"]     = '';
        $defaults["cube_face_{$i}_title"]           = '';
        $defaults["cube_face_{$i}_show_title"]      = true;

        // Face exterior background
        $defaults["cube_face_{$i}_bg_type"]        = 'default';
        $defaults["cube_face_{$i}_bg_color"]       = '#ffffff';
        $defaults["cube_face_{$i}_bg_image"]       = '';
        $defaults["cube_face_{$i}_bg_position"]    = 'center center';
        $defaults["cube_face_{$i}_bg_size"]        = 'cover';
        $defaults["cube_face_{$i}_grad_type"]      = 'linear';
        $defaults["cube_face_{$i}_grad_direction"] = '270deg';
        $defaults["cube_face_{$i}_grad_animate"]   = true;
        for ($c = 1; $c <= 4; $c++) {
            $defaults["cube_face_{$i}_grad_color{$c}"] = $grad_defaults[$c - 1];
        }

        // Face interior background
        $defaults["cube_face_{$i}_interior_enabled"] = false;
        $defaults["cube_face_{$i}_interior_type"]    = 'solid';
        $defaults["cube_face_{$i}_interior_color"]   = '#ffffff';
        $defaults["cube_face_{$i}_interior_image"]   = '';
        $defaults["cube_face_{$i}_interior_grad1"]   = '#f5f5f5';
        $defaults["cube_face_{$i}_interior_grad2"]   = '#e0e0e0';
    }

    return $defaults;
}

/**
 * Canonical default for one setting.
 *
 * @return mixed '' when the id is unknown
 */
function gcube_default(string $id) {
    return gcube_customizer_defaults()[$id] ?? '';
}

/**
 * Theme mod with the canonical default.
 *
 * @return mixed
 */
function gcube_mod(string $id) {
    return get_theme_mod($id, gcube_default($id));
}

/**
 * Face theme mod by DOM face id (0-5). Settings are keyed 1-6.
 *
 * @return mixed
 */
function gcube_face_mod(int $face_id, string $key) {
    return gcube_mod('cube_face_' . ($face_id + 1) . '_' . $key);
}

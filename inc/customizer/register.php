<?php
declare(strict_types=1);
/**
 * WordPress Theme Customizer Registration
 *
 * Orchestrates all gCube customizer sections via the parent theme's
 * gtemplate_register_customizer_sections extension point, so child
 * sections always register after gTemplate's shared sections.
 *
 * @package    gCube
 * @subpackage Customizer
 *
 * SECTION FILES:
 * - sections/sections.php      - Panel + section registration
 * - sections/colors.php        - Colors and gradients
 * - sections/pwa.php           - PWA settings
 * - sections/cube-settings.php - Cube geometry
 * - sections/faces.php         - Per-face content & backgrounds
 * - sections/navigation.php    - Nav button styling & mapping
 *
 * Setting IDs and defaults live in ../schema.php (single source of
 * truth shared with css-output, content-sync, PWA manifest, enqueue).
 *
 * Inherited from parent (gTemplate): logo, fonts, content expansion,
 * typography colors, site colors, contact info, post overlay — and all
 * gtemplate_sanitize_* callbacks.
 */

if (!defined('ABSPATH')) {
    exit;
}

$sections_dir = __DIR__ . '/sections/';
require_once $sections_dir . 'sections.php';
require_once $sections_dir . 'colors.php';
require_once $sections_dir . 'pwa.php';
require_once $sections_dir . 'cube-settings.php';
require_once $sections_dir . 'faces.php';
require_once $sections_dir . 'navigation.php';

/**
 * Register all gCube panels, sections, and settings
 *
 * @param WP_Customize_Manager $wp_customize Customizer manager instance
 * @return void
 */
function gcube_customize_register(WP_Customize_Manager $wp_customize): void {
    gcube_register_customizer_sections($wp_customize);

    gcube_register_colors_settings($wp_customize);
    gcube_register_pwa_settings($wp_customize);
    gcube_register_cube_settings($wp_customize);
    gcube_register_face_defaults($wp_customize);
    gcube_register_face_settings($wp_customize);
    gcube_register_navigation_styling($wp_customize);
    gcube_register_navigation_buttons($wp_customize);
}

add_action('gtemplate_register_customizer_sections', 'gcube_customize_register');

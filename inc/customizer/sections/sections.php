<?php
declare(strict_types=1);
/**
 * Customizer Panel + Section Registration
 *
 * One "Cube" panel groups all cube-specific sections; each face gets
 * its own section (gcube_face_1..6) instead of one monolithic list.
 * Colors and PWA stay top-level ('colors' is the core section).
 *
 * @package    gCube
 * @subpackage Customizer\Sections
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Face position labels, keyed by customizer face number (1-6).
 *
 * @return array<int, string>
 */
function gcube_face_positions(): array {
    return [
        1 => __('Top', 'gcube'),
        2 => __('Front (Default View)', 'gcube'),
        3 => __('Right', 'gcube'),
        4 => __('Back', 'gcube'),
        5 => __('Left', 'gcube'),
        6 => __('Bottom', 'gcube'),
    ];
}

/**
 * Register the cube panel and all gCube sections
 *
 * @param WP_Customize_Manager $wp_customize Customizer manager instance
 * @return void
 */
function gcube_register_customizer_sections(WP_Customize_Manager $wp_customize): void {
    // 'colors' section is owned by the parent (gTemplate site-colors.php);
    // gCube registers its cube-specific colors into it.

    // PWA Settings
    $wp_customize->add_section('gcube_pwa', [
        'title' => __('PWA Settings', 'gcube'),
        'priority' => 35,
    ]);

    // Cube panel — everything cube-shaped lives here
    $wp_customize->add_panel('gcube_cube', [
        'title' => __('Cube', 'gcube'),
        'description' => __('Cube geometry, faces, and navigation.', 'gcube'),
        'priority' => 150,
    ]);

    $wp_customize->add_section('cube_settings', [
        'title' => __('Cube Sizing', 'gcube'),
        'description' => __('Cube dimensions, perspective, and glass surface.', 'gcube'),
        'panel' => 'gcube_cube',
        'priority' => 10,
    ]);

    $wp_customize->add_section('gcube_faces_defaults', [
        'title' => __('Face Defaults', 'gcube'),
        'description' => __('How faces paint when a face does not override. Gradient palette lives in Colors.', 'gcube'),
        'panel' => 'gcube_cube',
        'priority' => 20,
    ]);

    // One section per face
    foreach (gcube_face_positions() as $i => $position) {
        $wp_customize->add_section("gcube_face_{$i}", [
            /* translators: 1: face number, 2: face position */
            'title' => sprintf(__('Face %1$d — %2$s', 'gcube'), $i, $position),
            'panel' => 'gcube_cube',
            'priority' => 30 + $i,
        ]);
    }

    $wp_customize->add_section('nav_button_content', [
        'title' => __('Navigation Buttons', 'gcube'),
        'description' => __('What each button does: label, target face, and content.', 'gcube'),
        'panel' => 'gcube_cube',
        'priority' => 50,
    ]);

    $wp_customize->add_section('nav_button_styling', [
        'title' => __('Navigation Styling', 'gcube'),
        'description' => __('How all buttons look: preset, colors, and dimensions.', 'gcube'),
        'panel' => 'gcube_cube',
        'priority' => 60,
    ]);
}

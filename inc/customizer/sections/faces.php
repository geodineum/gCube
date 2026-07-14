<?php
declare(strict_types=1);
/**
 * Customizer: Per-Face Sections
 *
 * Content, exterior background, and interior background for each of the
 * six faces. Each face registers into its own section (gcube_face_N);
 * controls only appear when the choices they depend on are active.
 *
 * @package    gCube
 * @subpackage Customizer\Sections
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Active-callback factory: control is visible when the face's $key
 * setting holds one of $values.
 */
function gcube_face_active(int $i, string $key, array $values): callable {
    return static function () use ($i, $key, $values): bool {
        return in_array(gcube_mod("cube_face_{$i}_{$key}"), $values, true);
    };
}

/**
 * Register face-default settings — how faces paint when a face does not
 * override (Background Type "Default")
 *
 * @param WP_Customize_Manager $wp_customize Customizer manager instance
 * @return void
 */
function gcube_register_face_defaults(WP_Customize_Manager $wp_customize): void {
    $wp_customize->add_setting('gcube_bg_style', [
        'default' => gcube_default('gcube_bg_style'),
        'sanitize_callback' => 'gtemplate_sanitize_option',
        'transport' => 'refresh',
    ]);
    $wp_customize->add_control('gcube_bg_style', [
        'label' => __('Face Background Style', 'gcube'),
        'description' => __('Applies to faces whose Background Type is "Default". The gradient palette is set in Colors.', 'gcube'),
        'section' => 'gcube_faces_defaults',
        'type' => 'select',
        'choices' => [
            'solid' => __('Solid Color (uses Gradient Color 1)', 'gcube'),
            'gradient' => __('Static Gradient (4 colors)', 'gcube'),
            'gradient_animated' => __('Animated Gradient (4 colors)', 'gcube'),
        ],
    ]);
}

/**
 * Register settings and controls for all 6 faces
 *
 * @param WP_Customize_Manager $wp_customize Customizer manager instance
 * @return void
 */
function gcube_register_face_settings(WP_Customize_Manager $wp_customize): void {
    foreach (gcube_face_positions() as $i => $position) {
        gcube_register_single_face($wp_customize, $i);
    }
}

/**
 * Register settings for a single face
 *
 * @param WP_Customize_Manager $wp_customize Customizer manager instance
 * @param int $i Face number (1-6)
 * @return void
 */
function gcube_register_single_face(WP_Customize_Manager $wp_customize, int $i): void {
    $section = "gcube_face_{$i}";

    // ── Content ───────────────────────────────────────────────────────

    $wp_customize->add_setting("cube_face_{$i}_enabled", [
        'default' => gcube_default("cube_face_{$i}_enabled"),
        'sanitize_callback' => 'absint',
        'transport' => 'refresh',
    ]);
    $wp_customize->add_control("cube_face_{$i}_enabled", [
        'label' => __('Enable Face', 'gcube'),
        'description' => __('Enable this face as a navigation target.', 'gcube'),
        'section' => $section,
        'type' => 'checkbox',
        'priority' => 10,
    ]);

    $wp_customize->add_setting("cube_face_{$i}_source", [
        'default' => gcube_default("cube_face_{$i}_source"),
        'sanitize_callback' => 'gtemplate_sanitize_option',
        'transport' => 'refresh',
    ]);
    $wp_customize->add_control("cube_face_{$i}_source", [
        'label' => __('Content Source', 'gcube'),
        'section' => $section,
        'type' => 'select',
        'priority' => 20,
        'choices' => [
            'glass' => __('Glass (Transparent)', 'gcube'),
            'glass_page' => __('Glass + Page', 'gcube'),
            'glass_custom' => __('Glass + Custom HTML', 'gcube'),
            'demo' => __('Demo Content', 'gcube'),
            'page' => __('WordPress Page', 'gcube'),
            'post' => __('WordPress Post', 'gcube'),
            'posts' => __('Multiple Posts', 'gcube'),
            'custom' => __('Custom HTML', 'gcube'),
        ],
    ]);

    $wp_customize->add_setting("cube_face_{$i}_content_id", [
        'default' => gcube_default("cube_face_{$i}_content_id"),
        'sanitize_callback' => 'absint',
        'transport' => 'refresh',
    ]);
    $wp_customize->add_control("cube_face_{$i}_content_id", [
        'label' => __('Page/Post ID', 'gcube'),
        'section' => $section,
        'type' => 'number',
        'priority' => 30,
        'input_attrs' => ['min' => 0],
        'active_callback' => gcube_face_active($i, 'source', ['page', 'post', 'glass_page']),
    ]);

    $wp_customize->add_setting("cube_face_{$i}_category_filter", [
        'default' => gcube_default("cube_face_{$i}_category_filter"),
        'sanitize_callback' => 'sanitize_text_field',
        'transport' => 'refresh',
    ]);
    $wp_customize->add_control("cube_face_{$i}_category_filter", [
        'label' => __('Category Filter', 'gcube'),
        'description' => __('Category IDs or slugs (comma-separated)', 'gcube'),
        'section' => $section,
        'type' => 'text',
        'priority' => 40,
        'active_callback' => gcube_face_active($i, 'source', ['posts']),
    ]);

    $wp_customize->add_setting("cube_face_{$i}_posts_per_page", [
        'default' => gcube_default("cube_face_{$i}_posts_per_page"),
        'sanitize_callback' => 'absint',
        'transport' => 'refresh',
    ]);
    $wp_customize->add_control("cube_face_{$i}_posts_per_page", [
        'label' => __('Posts Per Page', 'gcube'),
        'section' => $section,
        'type' => 'number',
        'priority' => 50,
        'input_attrs' => ['min' => 1, 'max' => 50],
        'active_callback' => gcube_face_active($i, 'source', ['posts']),
    ]);

    $wp_customize->add_setting("cube_face_{$i}_custom_html", [
        'default' => gcube_default("cube_face_{$i}_custom_html"),
        'sanitize_callback' => 'wp_kses_post',
        'transport' => 'refresh',
    ]);
    $wp_customize->add_control("cube_face_{$i}_custom_html", [
        'label' => __('Custom HTML', 'gcube'),
        'section' => $section,
        'type' => 'textarea',
        'priority' => 60,
        'active_callback' => gcube_face_active($i, 'source', ['custom', 'glass_custom']),
    ]);

    $wp_customize->add_setting("cube_face_{$i}_title", [
        'default' => gcube_default("cube_face_{$i}_title"),
        'sanitize_callback' => 'sanitize_text_field',
        'transport' => 'refresh',
    ]);
    $wp_customize->add_control("cube_face_{$i}_title", [
        'label' => __('Title Override', 'gcube'),
        'section' => $section,
        'type' => 'text',
        'priority' => 70,
    ]);

    $wp_customize->add_setting("cube_face_{$i}_show_title", [
        'default' => gcube_default("cube_face_{$i}_show_title"),
        'sanitize_callback' => 'absint',
        'transport' => 'refresh',
    ]);
    $wp_customize->add_control("cube_face_{$i}_show_title", [
        'label' => __('Show Title', 'gcube'),
        'section' => $section,
        'type' => 'checkbox',
        'priority' => 80,
    ]);

    gcube_register_face_background_settings($wp_customize, $i);
    gcube_register_face_interior_settings($wp_customize, $i);
}

/**
 * Register exterior background settings for a face
 */
function gcube_register_face_background_settings(WP_Customize_Manager $wp_customize, int $i): void {
    $section = "gcube_face_{$i}";

    $wp_customize->add_setting("cube_face_{$i}_bg_type", [
        'default' => gcube_default("cube_face_{$i}_bg_type"),
        'sanitize_callback' => 'gtemplate_sanitize_option',
        'transport' => 'refresh',
    ]);
    $wp_customize->add_control("cube_face_{$i}_bg_type", [
        'label' => __('Background Type', 'gcube'),
        'section' => $section,
        'type' => 'select',
        'priority' => 100,
        'choices' => [
            'default' => __('Default (Animated Gradient)', 'gcube'),
            'solid' => __('Solid Color', 'gcube'),
            'custom_gradient' => __('Custom Gradient', 'gcube'),
            'image' => __('Background Image', 'gcube'),
            'transparent' => __('Transparent', 'gcube'),
        ],
    ]);

    $wp_customize->add_setting("cube_face_{$i}_bg_color", [
        'default' => gcube_default("cube_face_{$i}_bg_color"),
        'sanitize_callback' => 'gtemplate_sanitize_hex_color',
        'transport' => 'refresh',
    ]);
    $wp_customize->add_control(new WP_Customize_Color_Control($wp_customize, "cube_face_{$i}_bg_color", [
        'label' => __('Background Color', 'gcube'),
        'section' => $section,
        'priority' => 110,
        'active_callback' => gcube_face_active($i, 'bg_type', ['solid']),
    ]));

    $wp_customize->add_setting("cube_face_{$i}_bg_image", [
        'default' => gcube_default("cube_face_{$i}_bg_image"),
        'sanitize_callback' => 'esc_url_raw',
        'transport' => 'refresh',
    ]);
    $wp_customize->add_control(new WP_Customize_Image_Control($wp_customize, "cube_face_{$i}_bg_image", [
        'label' => __('Background Image', 'gcube'),
        'section' => $section,
        'priority' => 120,
        'active_callback' => gcube_face_active($i, 'bg_type', ['image']),
    ]));

    $wp_customize->add_setting("cube_face_{$i}_bg_position", [
        'default' => gcube_default("cube_face_{$i}_bg_position"),
        'sanitize_callback' => 'gtemplate_sanitize_option',
        'transport' => 'refresh',
    ]);
    $wp_customize->add_control("cube_face_{$i}_bg_position", [
        'label' => __('Image Position', 'gcube'),
        'section' => $section,
        'type' => 'select',
        'priority' => 130,
        'active_callback' => gcube_face_active($i, 'bg_type', ['image']),
        'choices' => [
            'center center' => __('Center', 'gcube'),
            'top center' => __('Top', 'gcube'),
            'bottom center' => __('Bottom', 'gcube'),
            'left center' => __('Left', 'gcube'),
            'right center' => __('Right', 'gcube'),
            'top left' => __('Top Left', 'gcube'),
            'top right' => __('Top Right', 'gcube'),
            'bottom left' => __('Bottom Left', 'gcube'),
            'bottom right' => __('Bottom Right', 'gcube'),
        ],
    ]);

    $wp_customize->add_setting("cube_face_{$i}_bg_size", [
        'default' => gcube_default("cube_face_{$i}_bg_size"),
        'sanitize_callback' => 'gtemplate_sanitize_option',
        'transport' => 'refresh',
    ]);
    $wp_customize->add_control("cube_face_{$i}_bg_size", [
        'label' => __('Image Size', 'gcube'),
        'section' => $section,
        'type' => 'select',
        'priority' => 140,
        'active_callback' => gcube_face_active($i, 'bg_type', ['image']),
        'choices' => [
            'cover' => __('Cover', 'gcube'),
            'contain' => __('Contain', 'gcube'),
            'auto' => __('Auto', 'gcube'),
            '100% 100%' => __('Stretch', 'gcube'),
        ],
    ]);

    $wp_customize->add_setting("cube_face_{$i}_grad_type", [
        'default' => gcube_default("cube_face_{$i}_grad_type"),
        'sanitize_callback' => 'gtemplate_sanitize_option',
        'transport' => 'refresh',
    ]);
    $wp_customize->add_control("cube_face_{$i}_grad_type", [
        'label' => __('Gradient Type', 'gcube'),
        'section' => $section,
        'type' => 'select',
        'priority' => 150,
        'active_callback' => gcube_face_active($i, 'bg_type', ['custom_gradient']),
        'choices' => [
            'linear' => __('Linear', 'gcube'),
            'radial' => __('Radial (center)', 'gcube'),
            'radial_corner' => __('Radial (corner)', 'gcube'),
            'conic' => __('Conic', 'gcube'),
        ],
    ]);

    $wp_customize->add_setting("cube_face_{$i}_grad_direction", [
        'default' => gcube_default("cube_face_{$i}_grad_direction"),
        'sanitize_callback' => 'gtemplate_sanitize_option',
        'transport' => 'refresh',
    ]);
    $wp_customize->add_control("cube_face_{$i}_grad_direction", [
        'label' => __('Gradient Direction', 'gcube'),
        'section' => $section,
        'type' => 'select',
        'priority' => 160,
        'active_callback' => gcube_face_active($i, 'bg_type', ['custom_gradient']),
        'choices' => [
            '90deg' => __('→ Left to Right', 'gcube'),
            '270deg' => __('← Right to Left', 'gcube'),
            '180deg' => __('↓ Top to Bottom', 'gcube'),
            '0deg' => __('↑ Bottom to Top', 'gcube'),
            '135deg' => __('↘ Diagonal', 'gcube'),
            '315deg' => __('↖ Diagonal', 'gcube'),
            '45deg' => __('↗ Diagonal', 'gcube'),
            '225deg' => __('↙ Diagonal', 'gcube'),
        ],
    ]);

    for ($c = 1; $c <= 4; $c++) {
        $wp_customize->add_setting("cube_face_{$i}_grad_color{$c}", [
            'default' => gcube_default("cube_face_{$i}_grad_color{$c}"),
            'sanitize_callback' => 'gtemplate_sanitize_hex_color',
            'transport' => 'refresh',
        ]);
        $wp_customize->add_control(new WP_Customize_Color_Control($wp_customize, "cube_face_{$i}_grad_color{$c}", [
            'label' => sprintf(__('Gradient Color %d', 'gcube'), $c),
            'section' => $section,
            'priority' => 160 + $c,
            'active_callback' => gcube_face_active($i, 'bg_type', ['custom_gradient']),
        ]));
    }

    $wp_customize->add_setting("cube_face_{$i}_grad_animate", [
        'default' => gcube_default("cube_face_{$i}_grad_animate"),
        'sanitize_callback' => 'absint',
        'transport' => 'refresh',
    ]);
    $wp_customize->add_control("cube_face_{$i}_grad_animate", [
        'label' => __('Animate Gradient', 'gcube'),
        'section' => $section,
        'type' => 'checkbox',
        'priority' => 170,
        'active_callback' => gcube_face_active($i, 'bg_type', ['custom_gradient']),
    ]);

    // Face 4 (back) carries the global backside image — applies when its
    // Background Type is "Default"
    if ($i === 4) {
        $wp_customize->add_setting('cube_four_bg_image', [
            'default' => gcube_default('cube_four_bg_image'),
            'transport' => 'refresh',
            'sanitize_callback' => 'esc_url_raw',
        ]);
        $wp_customize->add_control(new WP_Customize_Image_Control($wp_customize, 'cube_four_bg_image', [
            'label' => __('Backside Image', 'gcube'),
            'description' => __('Shown when Background Type is "Default".', 'gcube'),
            'section' => $section,
            'priority' => 180,
            'active_callback' => gcube_face_active($i, 'bg_type', ['default']),
        ]));

        $wp_customize->add_setting('cube_four_bg_size', [
            'default' => gcube_default('cube_four_bg_size'),
            'transport' => 'refresh',
            'sanitize_callback' => 'gtemplate_sanitize_option',
        ]);
        $wp_customize->add_control('cube_four_bg_size', [
            'label' => __('Backside Image Size', 'gcube'),
            'section' => $section,
            'type' => 'select',
            'priority' => 181,
            'active_callback' => gcube_face_active($i, 'bg_type', ['default']),
            'choices' => [
                'cover' => __('Cover', 'gcube'),
                'contain' => __('Contain', 'gcube'),
                'auto' => __('Auto', 'gcube'),
                '100% 100%' => __('Stretch', 'gcube'),
            ],
        ]);
    }
}

/**
 * Register interior (backside) settings for a face
 */
function gcube_register_face_interior_settings(WP_Customize_Manager $wp_customize, int $i): void {
    $section = "gcube_face_{$i}";

    $interior_active = static function (array $types) use ($i): callable {
        return static function () use ($i, $types): bool {
            return (bool) gcube_mod("cube_face_{$i}_interior_enabled")
                && in_array(gcube_mod("cube_face_{$i}_interior_type"), $types, true);
        };
    };

    $wp_customize->add_setting("cube_face_{$i}_interior_enabled", [
        'default' => gcube_default("cube_face_{$i}_interior_enabled"),
        'sanitize_callback' => 'absint',
        'transport' => 'refresh',
    ]);
    $wp_customize->add_control("cube_face_{$i}_interior_enabled", [
        'label' => __('Enable Interior Background', 'gcube'),
        'description' => __('Show different background on inside of face', 'gcube'),
        'section' => $section,
        'type' => 'checkbox',
        'priority' => 200,
    ]);

    $wp_customize->add_setting("cube_face_{$i}_interior_type", [
        'default' => gcube_default("cube_face_{$i}_interior_type"),
        'sanitize_callback' => 'gtemplate_sanitize_option',
        'transport' => 'refresh',
    ]);
    $wp_customize->add_control("cube_face_{$i}_interior_type", [
        'label' => __('Interior Background Type', 'gcube'),
        'section' => $section,
        'type' => 'select',
        'priority' => 210,
        'active_callback' => static function () use ($i): bool {
            return (bool) gcube_mod("cube_face_{$i}_interior_enabled");
        },
        'choices' => [
            'solid' => __('Solid Color', 'gcube'),
            'gradient' => __('Gradient', 'gcube'),
            'image' => __('Image', 'gcube'),
        ],
    ]);

    $wp_customize->add_setting("cube_face_{$i}_interior_color", [
        'default' => gcube_default("cube_face_{$i}_interior_color"),
        'sanitize_callback' => 'gtemplate_sanitize_hex_color',
        'transport' => 'refresh',
    ]);
    $wp_customize->add_control(new WP_Customize_Color_Control($wp_customize, "cube_face_{$i}_interior_color", [
        'label' => __('Interior Color', 'gcube'),
        'section' => $section,
        'priority' => 220,
        'active_callback' => $interior_active(['solid', 'image']),
    ]));

    $wp_customize->add_setting("cube_face_{$i}_interior_image", [
        'default' => gcube_default("cube_face_{$i}_interior_image"),
        'sanitize_callback' => 'esc_url_raw',
        'transport' => 'refresh',
    ]);
    $wp_customize->add_control(new WP_Customize_Image_Control($wp_customize, "cube_face_{$i}_interior_image", [
        'label' => __('Interior Image', 'gcube'),
        'section' => $section,
        'priority' => 230,
        'active_callback' => $interior_active(['image']),
    ]));

    $wp_customize->add_setting("cube_face_{$i}_interior_grad1", [
        'default' => gcube_default("cube_face_{$i}_interior_grad1"),
        'sanitize_callback' => 'gtemplate_sanitize_hex_color',
        'transport' => 'refresh',
    ]);
    $wp_customize->add_control(new WP_Customize_Color_Control($wp_customize, "cube_face_{$i}_interior_grad1", [
        'label' => __('Interior Gradient 1', 'gcube'),
        'section' => $section,
        'priority' => 240,
        'active_callback' => $interior_active(['gradient']),
    ]));

    $wp_customize->add_setting("cube_face_{$i}_interior_grad2", [
        'default' => gcube_default("cube_face_{$i}_interior_grad2"),
        'sanitize_callback' => 'gtemplate_sanitize_hex_color',
        'transport' => 'refresh',
    ]);
    $wp_customize->add_control(new WP_Customize_Color_Control($wp_customize, "cube_face_{$i}_interior_grad2", [
        'label' => __('Interior Gradient 2', 'gcube'),
        'section' => $section,
        'priority' => 250,
        'active_callback' => $interior_active(['gradient']),
    ]));
}

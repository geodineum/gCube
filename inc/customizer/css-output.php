<?php
declare(strict_types=1);
/**
 * gCube Theme Customizer CSS Generation
 *
 * Outputs cube-specific dynamic CSS via gtemplate_dynamic_css filter.
 * Shared CSS vars (typography, post overlay, legacy compat) come from parent theme.
 *
 * gcube_face_surface() is the ONE place per-face backgrounds are
 * computed — both this filter and the content-sync bundle consume it.
 * All theme-mod reads resolve through schema.php accessors.
 *
 * @package     gCube
 * @subpackage  Customizer
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Shared surface context: global background style, glass front, global
 * gradient colors.
 *
 * @return array{bg_style:string, front_is_glass:bool, grad:array<int,string>, bg_size:string, animation:string}
 */
function gcube_surface_context(): array {
    $h = 'gtemplate_prepend_hash';
    $bg_style = gcube_mod('gcube_bg_style');
    return [
        'bg_style' => $bg_style,
        'front_is_glass' => in_array(gcube_mod('cube_face_2_source'), ['glass', 'glass_page', 'glass_custom'], true),
        'grad' => [
            1 => $h(gcube_mod('grad_color1')),
            2 => $h(gcube_mod('grad_color2')),
            3 => $h(gcube_mod('grad_color3')),
            4 => $h(gcube_mod('grad_color4')),
        ],
        'bg_size' => ($bg_style === 'gradient_animated') ? '800% 800%' : '100% 100%',
        'animation' => ($bg_style === 'gradient_animated') ? 'BGgrad 15s ease infinite' : 'none',
    ];
}

/**
 * Compute the exterior + interior surface for one face.
 *
 * @param int        $i   Face number (1-6, customizer numbering)
 * @param array|null $ctx gcube_surface_context() (computed when null)
 * @return array{front_bg:string, front_bg_size:string, front_animation:string, back_bg:string, back_bg_size:string}
 */
function gcube_face_surface(int $i, ?array $ctx = null): array {
    $h = 'gtemplate_prepend_hash';
    $ctx = $ctx ?? gcube_surface_context();

    $bg_type = gcube_mod("cube_face_{$i}_bg_type");

    $grad1 = $h(gcube_mod("cube_face_{$i}_grad_color1"));
    $grad2 = $h(gcube_mod("cube_face_{$i}_grad_color2"));
    $grad3 = $h(gcube_mod("cube_face_{$i}_grad_color3"));
    $grad4 = $h(gcube_mod("cube_face_{$i}_grad_color4"));

    $has_custom_colors = ($grad1 !== $ctx['grad'][1] || $grad2 !== $ctx['grad'][2] ||
                          $grad3 !== $ctx['grad'][3] || $grad4 !== $ctx['grad'][4]);

    $front_bg = '';
    $front_bg_size = $ctx['bg_size'];
    $front_animation = $ctx['animation'];

    if ($bg_type === 'solid') {
        $front_bg = $h(gcube_mod("cube_face_{$i}_bg_color"));
        $front_bg_size = 'auto';
        $front_animation = 'none';
    } elseif ($bg_type === 'custom_gradient') {
        $animate = gcube_mod("cube_face_{$i}_grad_animate");
        $grad_type = gcube_mod("cube_face_{$i}_grad_type");
        $grad_direction = gcube_mod("cube_face_{$i}_grad_direction");

        switch ($grad_type) {
            case 'radial':
                $front_bg = "radial-gradient(circle at center, {$grad1}, {$grad2}, {$grad3}, {$grad4})";
                $front_bg_size = '100% 100%';
                $front_animation = 'none';
                break;
            case 'radial_corner':
                $front_bg = "radial-gradient(ellipse at top left, {$grad1}, {$grad2}, {$grad3}, {$grad4})";
                $front_bg_size = '100% 100%';
                $front_animation = 'none';
                break;
            case 'conic':
                $front_bg = "conic-gradient(from {$grad_direction}, {$grad1}, {$grad2}, {$grad3}, {$grad4}, {$grad1})";
                $front_bg_size = '100% 100%';
                $front_animation = $animate ? 'BGspin 15s linear infinite' : 'none';
                break;
            case 'linear':
            default:
                $front_bg = "linear-gradient({$grad_direction}, {$grad1}, {$grad2}, {$grad3}, {$grad4})";
                $front_bg_size = $animate ? '800% 800%' : '100% 100%';
                $front_animation = $animate ? 'BGgrad 15s ease infinite' : 'none';
                break;
        }
    } elseif ($bg_type === 'image') {
        $bg_image = gcube_mod("cube_face_{$i}_bg_image");
        if (!empty($bg_image)) {
            $front_bg = "url('" . esc_url($bg_image) . "')";
            $front_bg_size = esc_attr(gcube_mod("cube_face_{$i}_bg_size"));
            $front_animation = 'none';
        }
    } elseif ($bg_type === 'transparent') {
        $front_bg = 'transparent';
        $front_bg_size = 'auto';
        $front_animation = 'none';
    } elseif ($bg_type === 'default') {
        if ($has_custom_colors) {
            $front_bg = "linear-gradient(270deg, {$grad1}, {$grad2}, {$grad3}, {$grad4})";
        }
    }

    // Face 2 (front) glass mode
    if ($i === 2 && $ctx['front_is_glass'] && $bg_type === 'default') {
        $front_bg = 'var(--semi-transparant)';
        $front_bg_size = 'auto';
        $front_animation = 'none';
    }

    // Face 4 (back) global image
    if ($i === 4 && $bg_type === 'default') {
        $cube_four_bg_image = gcube_mod('cube_four_bg_image');
        if (!empty($cube_four_bg_image)) {
            $front_bg = "url('" . esc_url($cube_four_bg_image) . "')";
            $front_bg_size = esc_attr(gcube_mod('cube_four_bg_size'));
            $front_animation = 'none';
        }
    }

    // Interior (back surface)
    $back_bg = '';
    $back_bg_size = 'auto';
    if (gcube_mod("cube_face_{$i}_interior_enabled")) {
        $interior_type = gcube_mod("cube_face_{$i}_interior_type");
        $interior_color = $h(gcube_mod("cube_face_{$i}_interior_color"));
        $interior_image = gcube_mod("cube_face_{$i}_interior_image");
        $interior_grad1 = $h(gcube_mod("cube_face_{$i}_interior_grad1"));
        $interior_grad2 = $h(gcube_mod("cube_face_{$i}_interior_grad2"));

        switch ($interior_type) {
            case 'image':
                $back_bg = !empty($interior_image) ? "url('" . esc_url($interior_image) . "')" : $interior_color;
                $back_bg_size = !empty($interior_image) ? 'cover' : 'auto';
                break;
            case 'gradient':
                $back_bg = "linear-gradient(135deg, {$interior_grad1}, {$interior_grad2})";
                $back_bg_size = '100% 100%';
                break;
            default:
                $back_bg = $interior_color;
                break;
        }
    }

    return [
        'front_bg' => $front_bg,
        'front_bg_size' => $front_bg_size,
        'front_animation' => $front_animation,
        'back_bg' => $back_bg,
        'back_bg_size' => $back_bg_size,
    ];
}

/**
 * Cube-specific CSS output via parent theme's filter.
 * Parent (gTemplate) outputs shared vars first, then this filter appends cube geometry.
 */
add_filter('gtemplate_dynamic_css', function ($css) {
    $h = 'gtemplate_prepend_hash'; // From parent theme

    // Face class mapping
    $face_classes = [
        1 => '.one',   // Top
        2 => '.two',   // Front
        3 => '.three', // Right
        4 => '.four',  // Back
        5 => '.five',  // Left
        6 => '.six',   // Bottom
    ];

    $ctx = gcube_surface_context();

    // ══════════════════════════════════════════
    // CUBE-SPECIFIC :root VARS
    // Parent already outputs: --gradcolor1-4, --color-bg, --color-txt,
    // --color-header, --scrollbar-color*, typography, post overlay.
    // We output cube-specific vars that override or extend.
    // ══════════════════════════════════════════
    $css .= ':root{';

    // Cube-specific colors (not in parent)
    $css .= '--color-border:' . esc_attr($h(gcube_mod('color_border'))) . ';';
    $css .= '--color-highlight:' . esc_attr($h(gcube_mod('color_highlight'))) . ';';
    $css .= '--color-hover:' . esc_attr($h(gcube_mod('color_hover'))) . ';';
    $css .= '--color-bg-button:' . esc_attr($h(gcube_mod('color_background_button'))) . ';';
    $css .= '--color-txt-button:' . esc_attr($h(gcube_mod('color_text_button'))) . ';';

    // Cube dimensions
    $css .= '--default-cubeheight:' . esc_attr(gcube_mod('default_cubeheight')) . ';';
    $css .= '--default-cubewidth:' . esc_attr(gcube_mod('default_cubewidth')) . ';';
    $css .= '--semi-transparant:' . esc_attr(gcube_mod('semi_transparant')) . ';';

    // Scene perspective
    $css .= '--scene-perspective:' . esc_attr(gcube_mod('perspective_scene')) . ';';
    $css .= '--scene-perspective-origin:' . esc_attr(gcube_mod('perspective_origin_scene')) . ';';

    // Navigation button vars (cube-specific defaults, override parent)
    $css .= '--nav-button-bg-color:' . esc_attr($h(gcube_mod('nav_button_bg_color'))) . ';';
    $css .= '--nav-button-text-color:' . esc_attr($h(gcube_mod('nav_button_text_color'))) . ';';
    $css .= '--nav-button-padding:' . esc_attr(gcube_mod('nav_button_padding')) . ';';
    $css .= '--nav-button-margin:' . esc_attr(gcube_mod('nav_button_margin')) . ';';
    $css .= '--nav-button-font-size:' . esc_attr(gcube_mod('nav_button_font_size')) . ';';
    $css .= '--nav-button-border-style:' . esc_attr(gcube_mod('nav_button_border_style')) . ';';
    $css .= '--nav-button-border-color:' . esc_attr($h(gcube_mod('nav_button_border_color'))) . ';';
    $css .= '--nav-button-border-width:' . esc_attr(gcube_mod('nav_button_border_width')) . ';';
    $css .= '--nav-button-border-radius:' . esc_attr(gcube_mod('nav_button_border_radius')) . ';';
    $css .= '--nav-button-hover-bg-color:' . esc_attr($h(gcube_mod('nav_button_hover_bg_color'))) . ';';
    $css .= '--nav-button-hover-text-color:' . esc_attr($h(gcube_mod('nav_button_hover_text_color'))) . ';';
    $css .= '--nav-button-min-width:' . esc_attr(gcube_mod('nav_button_min_width')) . ';';
    $css .= '--nav-button-max-height:' . esc_attr(gcube_mod('nav_button_max_height')) . ';';
    $css .= '--nav-wrapper-default-width:' . esc_attr(gcube_mod('nav_wrapper_width')) . ';';

    $css .= '}';

    // Typography (cube uses get_font_family helper)
    if (function_exists('get_font_family')) {
        $css .= 'body{font-family:' . get_font_family('body_font') . ';}';
        $css .= '.navButton{font-family:' . get_font_family('button_font') . ';}';
    }

    // ══════════════════════════════════════════
    // PER-FACE SURFACE VARIABLES
    // ══════════════════════════════════════════
    for ($i = 1; $i <= 6; $i++) {
        $face_class = $face_classes[$i];
        $surface = gcube_face_surface($i, $ctx);

        if (!empty($surface['front_bg']) || !empty($surface['back_bg'])) {
            $css .= "#cube {$face_class}{";
            if (!empty($surface['front_bg'])) {
                $css .= "--face-front-bg:{$surface['front_bg']};--face-front-bg-size:{$surface['front_bg_size']};--face-front-animation:{$surface['front_animation']};";
            }
            if (!empty($surface['back_bg'])) {
                $css .= "--face-back-bg:{$surface['back_bg']};--face-back-bg-size:{$surface['back_bg_size']};";
            }
            $css .= '}';
        }
    }

    // ══════════════════════════════════════════
    // GLOBAL BACKGROUND STYLE OVERRIDES
    // ══════════════════════════════════════════
    $front_is_glass = $ctx['front_is_glass'];
    if ($ctx['bg_style'] === 'solid') {
        $faces = '#cube .one,' . ($front_is_glass ? '' : '#cube .two,') . '#cube .three,#cube .five,#cube .six';
        $css .= "{$faces}{--face-front-bg:var(--gradcolor1);--face-front-bg-size:auto;--face-front-animation:none;}";
        if (!$front_is_glass) {
            $css .= '#cube .four{--face-front-bg:var(--gradcolor1);--face-front-bg-size:auto;--face-front-animation:none;}';
        }
    } elseif ($ctx['bg_style'] === 'gradient') {
        $faces = '#cube .one,' . ($front_is_glass ? '' : '#cube .two,') . '#cube .three,#cube .five,#cube .six';
        $css .= "{$faces}{--face-front-bg-size:100% 100%;--face-front-animation:none;}";
    }

    // ══════════════════════════════════════════
    // BUTTON STYLE PRESETS (shared helper from parent)
    // ══════════════════════════════════════════
    $button_style = gcube_mod('nav_button_style');
    if ($button_style !== 'classic' && function_exists('gtemplate_button_preset_css')) {
        $css .= gtemplate_button_preset_css(
            '.navButton',
            '.navButton:hover,.navButton:focus',
            '.navName',
            $button_style
        );
        // Hover color inversion for pill/outline (gCube uses .navName child selector)
        if ($button_style === 'pill' || $button_style === 'outline') {
            $css .= '.navButton:hover .navName,.navButton:focus .navName{color:var(--nav-button-hover-text-color,#fff)!important;}';
        }
    }
    // 'classic' — uses base navigation.css styles, no overrides

    return $css;
});

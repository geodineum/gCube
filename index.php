<?php
/**
 * Main template for gCube theme
 *
 * Renders a 3D cube interface with 6 faces powered by gNode Tera templates
 * for ultra-smooth, server-side rendered content.
 *
 * IMPORTANT: Content loads ON-DEMAND when faces are rotated into view.
 * This ensures glass mode faces stay transparent and no content is shown
 * on faces that haven't been explicitly navigated to.
 *
 * Navigation buttons support dynamic content mapping:
 * - data-face: Which cube face to rotate to (0-5)
 * - data-template: Template ID to render
 * - data-slug: Page/post slug to load content from
 *
 * All settings configurable via WordPress Customizer:
 * - Appearance > Customize > Cube Face Settings (face content)
 * - Appearance > Customize > Navigation Button Content (button → content mapping)
 *
 * @package gCube
 */

get_header();

// Nav button settings via the canonical schema (defaults live there)
$nav_buttons = [];
for ($i = 1; $i <= 6; $i++) {
    $nav_buttons[$i] = [
        'enabled' => (bool) gcube_mod("nav_button_{$i}_enabled"),
        'label' => gcube_mod("nav_button_{$i}_label"),
        'target_face' => gcube_mod("nav_button_{$i}_target_face"),
        'template' => gcube_mod("nav_button_{$i}_template"),
        'slug' => gcube_mod("nav_button_{$i}_slug"),
    ];
}

// Get cube face enabled states (for validation - disabled faces can't be targets)
$enabled_faces = [];
for ($i = 1; $i <= 6; $i++) {
    $enabled_faces[$i - 1] = (bool) gcube_mod("cube_face_{$i}_enabled");
}

/**
 * Generate nav button HTML with data attributes for dynamic content loading
 *
 * @param int $button_num Button number (1-6)
 * @param array $button_config Button configuration from customizer
 * @param array $enabled_faces Array of enabled face states (indexed 0-5)
 * @return string Button HTML (empty string if button is disabled)
 */
function gcube_render_nav_button($button_num, $button_config, $enabled_faces = []) {
    // Check if button is enabled
    if (!$button_config['enabled']) {
        return ''; // Don't render disabled buttons
    }

    $face_id = intval($button_config['target_face']);

    // Check if target face is enabled (warn but still render)
    if (!empty($enabled_faces) && isset($enabled_faces[$face_id]) && !$enabled_faces[$face_id]) {
        // Target face is disabled - log warning but render anyway
        // The face might be intentionally disabled for some users
        error_log("gCube: Nav button {$button_num} targets disabled face {$face_id}");
    }

    $template = esc_attr($button_config['template']);
    $slug = esc_attr($button_config['slug']);
    $label = esc_html($button_config['label']);

    // Build data attributes
    $data_attrs = 'data-face="' . $face_id . '"';
    $data_attrs .= ' data-button="' . $button_num . '"';
    if (!empty($template)) {
        $data_attrs .= ' data-template="' . $template . '"';
    }
    if (!empty($slug)) {
        $data_attrs .= ' data-slug="' . $slug . '"';
    }

    // Map button number to button ID (for CSS styling)
    $button_names = [1 => 'one', 2 => 'two', 3 => 'three', 4 => 'four', 5 => 'five', 6 => 'six'];
    $button_id = 'button_' . $button_names[$button_num];

    return sprintf(
        '<div id="%s" class="navButton nav-button" %s>
            <button class="navName">%s</button>
        </div>',
        $button_id,
        $data_attrs,
        $label
    );
}
?>

<main id="primary" class="site-main">
    <!-- Navigation Buttons - Left Side (buttons 1-3) -->
    <div id="wrapper_left">
        <?php
        echo gcube_render_nav_button(1, $nav_buttons[1], $enabled_faces);
        echo gcube_render_nav_button(2, $nav_buttons[2], $enabled_faces);
        echo gcube_render_nav_button(3, $nav_buttons[3], $enabled_faces);
        ?>
    </div>

    <!-- CRITICAL: Use #scene and #cube to match the expected structure -->
    <div id="scene">
        <div id="cube">
            <?php
            // Render 6 cube faces with the expected class names
            // Face ID (0-5) maps to customizer face number (1-6)
            $faces = [
                0 => ['class' => 'one', 'position' => 'top', 'customizer_num' => 1],
                1 => ['class' => 'two', 'position' => 'front', 'customizer_num' => 2],
                2 => ['class' => 'three', 'position' => 'right', 'customizer_num' => 3],
                3 => ['class' => 'four', 'position' => 'back', 'customizer_num' => 4],
                4 => ['class' => 'five', 'position' => 'left', 'customizer_num' => 5],
                5 => ['class' => 'six', 'position' => 'bottom', 'customizer_num' => 6]
            ];

            foreach ($faces as $face_id => $face_info) {
                $face_class = esc_attr($face_info['class']);
                $position = $face_info['position'];
                $customizer_num = $face_info['customizer_num'];

                // Mark front face (face 1) as initially visible, others hidden
                $visibility_class = ($face_id === 1) ? 'face-visible' : 'face-hidden';

                // Check if this face is in glass mode (transparent)
                $source = gcube_mod("cube_face_{$customizer_num}_source");
                $is_pure_glass = ($source === 'glass'); // No content at all
                $is_glass_with_content = in_array($source, ['glass_page', 'glass_custom']); // Transparent + content

                // Build data attributes
                // Pure glass faces are pre-marked as loaded (they never load content)
                // Glass+content and non-glass faces load content on-demand
                $data_attrs = 'data-face-id="' . esc_attr($face_id) . '" data-customizer-num="' . esc_attr($customizer_num) . '"';
                if ($is_pure_glass) {
                    $data_attrs .= ' data-glass="true" data-loaded="true"';
                } elseif ($is_glass_with_content) {
                    $data_attrs .= ' data-glass="true" data-glass-content="true" data-loaded="false"';
                } else {
                    $data_attrs .= ' data-loaded="false"';
                }

                echo '<div class="face ' . $face_class . ' ' . $visibility_class . '" id="face' . $face_id . '" ' . $data_attrs . '>';

                // For pure glass mode: render empty container (complete transparency)
                // For glass+content: render empty container with transparent styling, content loads on-demand
                // For non-glass: render empty container, content loads on-demand when rotated to
                if ($is_pure_glass) {
                    // Pure glass mode: completely empty for see-through effect
                    echo '<div class="cube-face-container glass-mode"></div>';
                } elseif ($is_glass_with_content) {
                    // Glass with content: transparent background but loads content on-demand
                    echo '<div class="cube-face-container glass-mode glass-with-content on-demand-content">';
                    echo '<div class="cube-face-content"></div>';
                    echo '</div>';
                } else {
                    // Non-glass: empty container, content loads on-demand when face is rotated to
                    echo '<div class="cube-face-container on-demand-content">';
                    echo '<div class="cube-face-content"></div>';
                    echo '</div>';
                }

                echo '</div>';
            }
            ?>
        </div><!-- #cube -->
    </div><!-- #scene -->

    <!-- Navigation Buttons - Right Side (buttons 4-6) -->
    <div id="wrapper_right">
        <?php
        echo gcube_render_nav_button(4, $nav_buttons[4], $enabled_faces);
        echo gcube_render_nav_button(5, $nav_buttons[5], $enabled_faces);
        echo gcube_render_nav_button(6, $nav_buttons[6], $enabled_faces);
        ?>
    </div>
</main><!-- #primary -->

<?php
get_footer();

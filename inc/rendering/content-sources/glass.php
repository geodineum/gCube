<?php
declare(strict_types=1);
/**
 * Glass Mode Content Sources
 *
 * Renders content with transparent background for see-through effect.
 * A glass front face - see-through but with a text overlay.
 *
 * @package    gCube
 * @subpackage Rendering\ContentSources
 * @since      2.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Get glass mode page content (transparent background with page overlay)
 *
 * @since 1.0.0
 * @param int    $page_id        WordPress page ID
 * @param string $title_override Optional title override
 * @param bool   $show_title     Whether to show the title
 * @return string HTML content with glass styling
 */
function gcube_get_glass_page_content(int $page_id, string $title_override = '', bool $show_title = true): string {
    if ($page_id <= 0) {
        return gtemplate_get_empty_content_message('glass_page');
    }

    $page = get_post($page_id);
    if (!$page || $page->post_type !== 'page' || $page->post_status !== 'publish') {
        return gtemplate_get_empty_content_message('glass_page', $page_id);
    }

    $title = !empty($title_override) ? $title_override : $page->post_title;
    $content = apply_filters('the_content', $page->post_content);

    ob_start();
    ?>
    <div class="face-content face-content-glass-page glass-content" data-source="glass_page" data-content-id="<?php echo esc_attr($page_id); ?>">
        <div class="glass-text-overlay">
            <?php if ($show_title): ?>
            <h2 class="face-content-title glass-title"><?php echo esc_html($title); ?></h2>
            <?php endif; ?>

            <div class="face-content-body glass-body">
                <?php echo $content; ?>
            </div>
        </div>
    </div>
    <?php
    return ob_get_clean();
}

/**
 * Get glass mode custom content (transparent background with custom HTML overlay)
 *
 * @since 1.0.0
 * @param string $html           Custom HTML content
 * @param string $title_override Optional title
 * @param bool   $show_title     Whether to show the title
 * @return string HTML content with glass styling
 */
function gcube_get_glass_custom_content(string $html, string $title_override = '', bool $show_title = true): string {
    if (empty($html)) {
        return gtemplate_get_empty_content_message('glass_custom');
    }

    ob_start();
    ?>
    <div class="face-content face-content-glass-custom glass-content" data-source="glass_custom">
        <div class="glass-text-overlay">
            <?php if ($show_title && !empty($title_override)): ?>
            <h2 class="face-content-title glass-title"><?php echo esc_html($title_override); ?></h2>
            <?php endif; ?>

            <div class="face-content-body glass-body">
                <?php echo wp_kses_post($html); ?>
            </div>
        </div>
    </div>
    <?php
    return ob_get_clean();
}

/**
 * Source handler: Glass (transparent, no content by design —
 * mirrors the bundle tier's `case 'glass'` which ships empty HTML).
 */
function gcube_source_glass($face_id, $prefix, $title, $show_title) {
    return '';
}

/**
 * Source handler: Glass + Page
 */
function gcube_source_glass_page($face_id, $prefix, $title, $show_title) {
    $content_id = (int) get_theme_mod("{$prefix}_{$face_id}_content_id", 0);
    return gcube_get_glass_page_content($content_id, $title, $show_title);
}

/**
 * Source handler: Glass + Custom HTML
 */
function gcube_source_glass_custom($face_id, $prefix, $title, $show_title) {
    $custom_html = (string) get_theme_mod("{$prefix}_{$face_id}_custom_html", '');
    return gcube_get_glass_custom_content($custom_html, $title, $show_title);
}

/**
 * Register the glass sources with the parent's content-source router so the
 * PHP-fallback rendering tier resolves them instead of falling back to demo
 * content. The customizer offers glass/glass_page/glass_custom; the bundle
 * tier already handled them inline (content-sync.php) — this closes the gap
 * in the third tier.
 */
add_filter('gtemplate_content_sources', function(array $sources): array {
    $sources['glass']        = 'gcube_source_glass';
    $sources['glass_page']   = 'gcube_source_glass_page';
    $sources['glass_custom'] = 'gcube_source_glass_custom';
    return $sources;
});

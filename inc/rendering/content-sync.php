<?php
declare(strict_types=1);
/**
 * WordPress → gNode Template Synchronization
 *
 * Automatically converts WordPress pages to Tera templates and registers
 * them with gNode daemon for Tera template rendering (~50ms typical).
 *
 * This is the core of gCube's dynamic content loading system, enabling:
 * - Fast cube face rendering via gNode (50-200ms typical)
 * - Smooth iOS scrolling (60fps, no lag)
 * - HTMX lazy loading (progressive enhancement)
 * - WordPress WYSIWYG content editing
 *
 * @package gCube
 * @since 1.1.0
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Convert WordPress page to Tera template
 *
 * Generates a Tera template with WordPress content, featured images,
 * and metadata. Optimized for cube face rendering.
 *
 * @param int $post_id WordPress page ID
 * @return string|null Tera template content or null on failure
 */
function gcube_page_to_tera_template($post_id) {
    $post = get_post($post_id);

    if (!$post || $post->post_type !== 'page') {
        return null;
    }

    // Build Tera template with WordPress content
    // Uses Tera syntax for dynamic variables
    $template = <<<'TERA'
{# Auto-generated from WordPress Page #}
{# Page ID: {{ page_id }} - {{ title }} #}
<div class="cube-face-content wordpress-page" data-page-id="{{ page_id }}" data-slug="{{ slug }}">
    <header class="face-header">
        <h2 class="face-title">{{ title }}</h2>

        {% if featured_image %}
        <div class="face-featured-image">
            <img src="{{ featured_image }}"
                 alt="{{ title }}"
                 loading="lazy"
                 width="{{ featured_image_width }}"
                 height="{{ featured_image_height }}">
        </div>
        {% endif %}
    </header>

    <main class="face-body">
        {% if excerpt %}
        <div class="page-excerpt">
            <p>{{ excerpt }}</p>
        </div>
        {% endif %}

        <div class="page-content">
            {{ content | safe }}
        </div>

        {% if author or date %}
        <div class="page-meta">
            {% if author %}
            <span class="author">By {{ author }}</span>
            {% endif %}
            {% if date %}
            <time class="date" datetime="{{ date_iso }}">{{ date }}</time>
            {% endif %}
        </div>
        {% endif %}
    </main>

    <footer class="face-footer">
        {% if permalink %}
        <a href="{{ permalink }}" class="read-more">
            Read Full Page <span aria-hidden="true">&rarr;</span>
        </a>
        {% endif %}

        <small class="site-credit">
            <span>{{ blog_name }}</span>
            {% if updated %}
            <span class="updated">Updated: {{ updated }}</span>
            {% endif %}
        </small>
    </footer>
</div>
TERA;

    return $template;
}

/**
 * Register WordPress page template with gNode daemon
 *
 * Converts page to Tera template and registers it for server-side rendering.
 * Includes distributed locking to prevent race conditions.
 *
 * @param int $post_id WordPress page ID
 * @return bool Success status
 */
function gcube_register_page_template($post_id) {
    global $gCore;

    $post = get_post($post_id);

    if (!$post || $post->post_type !== 'page' || $post->post_status !== 'publish') {
        return false;
    }

    $gNode = gtemplate_gnode();
    if (!$gNode) {
        error_log("gCube: Cannot register page template, gNode unavailable (page: {$post->post_title})");
        return false;
    }

    // Acquire distributed lock for template registration
    $cache = null;
    $lock = null;

    try {
        $cache = $gCore->getService('Cache');

        if ($cache) {
            $lock_key = "gcube:page_template_reg:{$post_id}";
            $lock = $cache->acquireLock($lock_key, 15);  // 15 second timeout

            if (!$lock) {
                error_log("gCube: Template registration for page {$post_id} already in progress");
                return false;
            }
        }
    } catch (\Throwable $e) {
        error_log("gCube: Lock acquisition failed, proceeding anyway: " . $e->getMessage());
    }

    try {
        // Generate Tera template
        $template_content = gcube_page_to_tera_template($post_id);

        if (!$template_content) {
            return false;
        }

        // Template ID format: wp_page_{post_id}
        $template_id = "wp_page_{$post_id}";

        // Register with gNode
        $result = $gNode->registerTemplate($template_id, $template_content);

        if ($result) {
            error_log("gCube: ✓ Registered template '{$template_id}' for page: {$post->post_title}");

            // Cache template variables for fast rendering
            gcube_cache_page_variables($post_id);

            return true;
        } else {
            error_log("gCube: ✗ Failed to register template '{$template_id}'");
            return false;
        }

    } catch (\Throwable $e) {
        error_log("gCube: Error registering template for page {$post_id}: " . $e->getMessage());
        return false;

    } finally {
        // Always release lock
        if ($lock && $cache) {
            try {
                $cache->releaseLock($lock);
            } catch (\Throwable $e) {
                error_log("gCube: Failed to release lock: " . $e->getMessage());
            }
        }
    }
}

/**
 * Cache page variables for fast template rendering
 *
 * Pre-computes all template variables and stores them in ValKey
 * for <1ms retrieval during rendering.
 *
 * @param int $post_id WordPress page ID
 */
function gcube_cache_page_variables($post_id) {
    global $gCore;

    $post = get_post($post_id);

    if (!$post) {
        return;
    }

    // Get featured image dimensions
    $featured_image_id = get_post_thumbnail_id($post);
    $featured_image_meta = wp_get_attachment_metadata($featured_image_id);

    // Build template variables
    $variables = [
        'page_id' => $post_id,
        'slug' => $post->post_name,
        'title' => $post->post_title,
        'content' => apply_filters('the_content', $post->post_content),
        'excerpt' => $post->post_excerpt ?: wp_trim_words($post->post_content, 30),
        'author' => get_the_author_meta('display_name', $post->post_author),
        'date' => get_the_date('', $post),
        'date_iso' => get_the_date('c', $post),
        'updated' => get_the_modified_date('', $post),
        'permalink' => get_permalink($post),
        'featured_image' => get_the_post_thumbnail_url($post, 'large'),
        'featured_image_width' => $featured_image_meta['width'] ?? null,
        'featured_image_height' => $featured_image_meta['height'] ?? null,
        'blog_name' => get_bloginfo('name'),
        'timestamp' => time()
    ];

    // Cache via gCore CacheManager
    try {
        $cache = $gCore->getService('Cache');

        if ($cache) {
            $cache_key = "gcube:page_vars:{$post_id}";
            $cache->set($cache_key, $variables, 3600);  // 1 hour TTL
        }
    } catch (\Throwable $e) {
        error_log("gCube: Failed to cache page variables: " . $e->getMessage());
    }
}

/**
 * Get cached page variables with fallback
 *
 * @param int $post_id WordPress page ID
 * @return array Template variables
 */
function gcube_get_page_variables($post_id) {
    global $gCore;

    try {
        $cache = $gCore->getService('Cache');

        if ($cache) {
            $cache_key = "gcube:page_vars:{$post_id}";
            $cached = $cache->get($cache_key);

            if ($cached && is_array($cached)) {
                return $cached;
            }
        }
    } catch (\Throwable $e) {
        error_log("gCube: Cache retrieval failed: " . $e->getMessage());
    }

    // Cache miss - rebuild
    gcube_cache_page_variables($post_id);

    // Recursive call to get freshly cached data
    return gcube_get_page_variables_direct($post_id);
}

/**
 * Get page variables directly without caching (fallback)
 *
 * @param int $post_id WordPress page ID
 * @return array Template variables
 */
function gcube_get_page_variables_direct($post_id) {
    $post = get_post($post_id);

    if (!$post) {
        return [];
    }

    $featured_image_id = get_post_thumbnail_id($post);
    $featured_image_meta = wp_get_attachment_metadata($featured_image_id);

    return [
        'page_id' => $post_id,
        'slug' => $post->post_name,
        'title' => $post->post_title,
        'content' => apply_filters('the_content', $post->post_content),
        'excerpt' => $post->post_excerpt ?: wp_trim_words($post->post_content, 30),
        'author' => get_the_author_meta('display_name', $post->post_author),
        'date' => get_the_date('', $post),
        'date_iso' => get_the_date('c', $post),
        'updated' => get_the_modified_date('', $post),
        'permalink' => get_permalink($post),
        'featured_image' => get_the_post_thumbnail_url($post, 'large'),
        'featured_image_width' => $featured_image_meta['width'] ?? null,
        'featured_image_height' => $featured_image_meta['height'] ?? null,
        'blog_name' => get_bloginfo('name'),
        'timestamp' => time()
    ];
}

/**
 * Auto-register page template on save
 *
 * Hooks into WordPress save_post action to automatically
 * sync page content to gNode templates.
 */
function gcube_auto_register_on_save($post_id, $post, $update) {
    // Skip auto-saves and revisions
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
        return;
    }

    if (wp_is_post_revision($post_id)) {
        return;
    }

    // Only process published pages
    if ($post->post_type === 'page' && $post->post_status === 'publish') {
        // Defer to next request to avoid slowing down save
        wp_schedule_single_event(time() + 5, 'gcube_register_template_event', [$post_id]);
    }
}
add_action('save_post', 'gcube_auto_register_on_save', 20, 3);

/**
 * Deferred template registration event
 */
function gcube_deferred_template_registration($post_id) {
    gcube_register_page_template($post_id);
}
add_action('gcube_register_template_event', 'gcube_deferred_template_registration');

/**
 * Invalidate cache on page update
 */
function gcube_invalidate_page_cache($post_id) {
    global $gCore;

    try {
        $cache = $gCore->getService('Cache');

        if ($cache) {
            $cache_key = "gcube:page_vars:{$post_id}";
            $cache->delete($cache_key);
            error_log("gCube: Invalidated cache for page {$post_id}");
        }
    } catch (\Throwable $e) {
        error_log("gCube: Cache invalidation failed: " . $e->getMessage());
    }
}
add_action('save_post_page', 'gcube_invalidate_page_cache', 10);

/**
 * Delete template on page delete
 */
function gcube_delete_page_template($post_id) {
    $post = get_post($post_id);

    if ($post && $post->post_type === 'page') {
        $template_id = "wp_page_{$post_id}";
        error_log("gCube: Page {$post_id} deleted, template '{$template_id}' orphaned (no gNode deleteTemplate method)");

        // Invalidate cache
        gcube_invalidate_page_cache($post_id);
    }
}
add_action('delete_post', 'gcube_delete_page_template');

/**
 * Bulk register all existing pages
 *
 * Useful for initial setup or after gNode daemon restart.
 * Can be triggered via WP-CLI or admin action.
 *
 * @return int Number of pages registered
 */
function gcube_bulk_register_pages() {
    $pages = get_pages([
        'post_status' => 'publish',
        'number' => 0  // All pages
    ]);

    $registered = 0;
    $failed = 0;

    foreach ($pages as $page) {
        if (gcube_register_page_template($page->ID)) {
            $registered++;
        } else {
            $failed++;
        }

        // Prevent timeout on large sites
        if ($registered % 10 === 0) {
            usleep(100000);  // 100ms pause every 10 pages
        }
    }

    error_log("gCube: Bulk registration complete - {$registered} registered, {$failed} failed");

    return $registered;
}

/**
 * Get page ID for cube face (mapping system)
 *
 * Maps cube face (0-5) to WordPress page ID.
 * Uses custom mapping from theme options, or auto-assigns recent pages.
 *
 * @param int $face_id Cube face ID (0-5)
 * @return int|null WordPress page ID or null
 */
function gcube_get_face_page_mapping($face_id) {
    // Validate face ID
    if ($face_id < 0 || $face_id > 5) {
        return null;
    }

    // Get custom mapping from theme options
    $mapping = get_option('gcube_face_mapping', []);

    if (isset($mapping[$face_id]) && is_numeric($mapping[$face_id])) {
        return (int) $mapping[$face_id];
    }

    // Auto-mapping: Use most recent pages ordered by menu_order
    $pages = get_pages([
        'post_status' => 'publish',
        'number' => 6,
        'sort_column' => 'menu_order',
        'sort_order' => 'ASC'
    ]);

    if (isset($pages[$face_id])) {
        return $pages[$face_id]->ID;
    }

    return null;
}

/**
 * Set custom face-to-page mapping
 *
 * @param int $face_id Cube face ID (0-5)
 * @param int $page_id WordPress page ID
 * @return bool Success status
 */
function gcube_set_face_mapping($face_id, $page_id) {
    if ($face_id < 0 || $face_id > 5) {
        return false;
    }

    $mapping = get_option('gcube_face_mapping', []);
    $mapping[$face_id] = (int) $page_id;

    return update_option('gcube_face_mapping', $mapping);
}

/**
 * Sync cube face mapping to ValKey for gNode bundle builder
 *
 * This function pre-renders all 6 cube faces and stores them in ValKey
 * at {site_id}:gnode:face_mapping. The gNode daemon's bundle builder reads
 * this to create compressed bundles for fast retrieval (~5ms typical).
 *
 * @return bool Success status
 */
function gcube_sync_face_mapping_to_valkey(): bool {
    try {
        $site_id = gtemplate_get_site_id();

        // Get gNode client via gCore
        global $gCore;
        if (!$gCore || !method_exists($gCore, 'getService')) {
            error_log("gCube: Cannot sync face mapping - gCore not available");
            return false;
        }

        $gNodeClient = $gCore->getService('gnode_client');
        if (!$gNodeClient) {
            error_log("gCube: Cannot sync face mapping - gNode client not available");
            return false;
        }

        // Build face data for all 6 faces
        $faces = [];
        $position_names = ['top', 'front', 'right', 'back', 'left', 'bottom'];
        $css_classes = ['one', 'two', 'three', 'four', 'five', 'six'];

        for ($i = 0; $i < 6; $i++) {
            $face_data = gcube_build_face_data_for_bundle($i, $position_names[$i], $css_classes[$i]);
            $faces[] = $face_data;
        }

        // Build complete mapping structure (matches bundle builder expectations)
        $mapping = [
            'site_id' => $site_id,
            'faces' => $faces,
            'metadata' => [
                'site_name' => get_bloginfo('name'),
                'site_url' => get_site_url(),
                'description' => get_bloginfo('description'),
                'theme_version' => defined('GCUBE_VERSION') ? GCUBE_VERSION : '1.0.0',
                'synced_at' => time(),
            ],
            'navigation' => gcube_get_navigation_for_bundle(),
            'posts' => gcube_get_recent_posts_for_bundle(),
        ];

        // Store in ValKey under gNode namespace for ACL compatibility
        $key = "{" . $site_id . "}:gnode:face_mapping";
        $json = json_encode($mapping, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $ttl = 0;  // No TTL - persist until updated

        // Use fcall through gCore's gNode client (ACL-compliant)
        $result = $gNodeClient->fcall('GNODE_CACHE_SET', [], [$key, $json, $ttl, $site_id]);

        if ($result !== false && $result !== null) {
            error_log("gCube: Face mapping synced to ValKey ({$key}) - " . strlen($json) . " bytes");

            // Invalidate keybased-client bundle cache — folded in from the
            // now-removed gcube_sync_face_configs_to_valkey hook so this
            // single canonical hook performs both invalidation paths.
            if (function_exists('gcube_invalidate_bundle')) {
                gcube_invalidate_bundle();
            }

            // Trigger bundle rebuild via invalidation event
            gcube_trigger_bundle_rebuild($site_id);

            // Sync to AssetManager manifest (new manifest-driven builder)
            $assetManager = $gCore->getService('AssetManager');
            if ($assetManager && $assetManager->isInitialized()) {
                $assetManager->syncFaceMapping($mapping);
            }

            return true;
        }

        error_log("gCube: Failed to store face mapping in ValKey");
        return false;

    } catch (\Throwable $e) {
        error_log("gCube: Face mapping sync error: " . $e->getMessage());
        return false;
    }
}

/**
 * Build face data for a single cube face
 *
 * @param int $face_id Face ID (0-5)
 * @param string $position Position name (top, front, right, back, left, bottom)
 * @param string $css_class CSS class (one, two, three, four, five, six)
 * @return array Face data structure
 */
function gcube_build_face_data_for_bundle(int $face_id, string $position, string $css_class): array {
    // Face configuration via the canonical schema. gcube_face_mod owns the
    // DOM-id (0-5) → setting-number (1-6) translation; the pre-schema reads
    // plugged the DOM id straight into setting names (cube_face_0_* …) and
    // used names nothing registers (_label/_category/_posts_count).
    $source = gcube_face_mod($face_id, 'source');
    $content_id = (int) gcube_face_mod($face_id, 'content_id');
    $label = gcube_face_mod($face_id, 'title') ?: ucfirst($position);
    $enabled = (bool) gcube_face_mod($face_id, 'enabled');

    $face_data = [
        'id' => $face_id,
        'label' => $label,
        'source' => $source,
        'enabled' => $enabled,
        'position' => $position,
        'css_class' => $css_class,
    ];

    // Pre-render content based on source type (all registered sources:
    // glass/glass_page/glass_custom/demo/page/post/posts/custom)
    switch ($source) {
        case 'page':
        case 'post':
        case 'glass_page':
            $type = ($source === 'post') ? 'post' : 'page';
            if ($content_id > 0) {
                $face_data['content_id'] = $content_id;
                $face_data['template_id'] = "wp_{$type}_{$content_id}";
                $face_data['html'] = gcube_render_content_for_bundle($content_id, $type);
            } else {
                $face_data['html'] = gcube_get_demo_content_for_bundle($face_id, $label);
            }
            break;

        case 'posts':
            $category = gcube_face_mod($face_id, 'category_filter');
            $posts_per_page = (int) gcube_face_mod($face_id, 'posts_per_page');
            $face_data['category_filter'] = $category;
            $face_data['posts_per_page'] = $posts_per_page;
            $face_data['html'] = gcube_render_posts_list_for_bundle($category, $posts_per_page, $label);
            break;

        case 'custom':
        case 'glass_custom':
            $face_data['html'] = wp_kses_post((string) gcube_face_mod($face_id, 'custom_html'));
            break;

        case 'glass':
            // Pure glass never loads content
            $face_data['html'] = '';
            break;

        case 'demo':
        default:
            $face_data['html'] = gcube_get_demo_content_for_bundle($face_id, $label);
            break;
    }

    // Add CSS (background styles)
    $face_data['css'] = gcube_get_face_css_for_bundle($face_id);
    $face_data['js'] = null;

    return $face_data;
}

/**
 * Render WordPress content for bundle
 *
 * @param int $content_id Post/Page ID
 * @param string $type Content type (page or post)
 * @return string Rendered HTML
 */
function gcube_render_content_for_bundle(int $content_id, string $type): string {
    $post = get_post($content_id);
    if (!$post) {
        return '<div class="face-content error">Content not found</div>';
    }

    $content = apply_filters('the_content', $post->post_content);

    return sprintf(
        '<article class="face-content %s" data-id="%d">
            <header class="entry-header">
                <h1 class="entry-title">%s</h1>
            </header>
            <div class="entry-content">%s</div>
        </article>',
        esc_attr($type),
        $content_id,
        esc_html($post->post_title),
        $content
    );
}

/**
 * Render posts list for bundle
 *
 * @param string $category Category slug filter
 * @param int $count Number of posts
 * @param string $label Face label
 * @return string Rendered HTML
 */
function gcube_render_posts_list_for_bundle(string $category, int $count, string $label): string {
    $args = [
        'post_type' => 'post',
        'post_status' => 'publish',
        'posts_per_page' => $count,
    ];

    if (!empty($category)) {
        $args['category_name'] = $category;
    }

    $query = new \WP_Query($args);
    $html = '<div class="face-posts-list"><h2>' . esc_html($label) . '</h2><ul class="posts-grid">';

    if ($query->have_posts()) {
        while ($query->have_posts()) {
            $query->the_post();
            $html .= sprintf(
                '<li class="post-item"><a href="%s"><h3>%s</h3><span class="date">%s</span></a></li>',
                get_permalink(),
                get_the_title(),
                get_the_date()
            );
        }
        wp_reset_postdata();
    } else {
        $html .= '<li class="no-posts">No posts found</li>';
    }

    $html .= '</ul></div>';
    return $html;
}

/**
 * Get demo content for bundle
 *
 * @param int $face_id Face ID
 * @param string $label Face label
 * @return string Demo HTML content
 */
function gcube_get_demo_content_for_bundle(int $face_id, string $label): string {
    $position_names = ['top', 'front', 'right', 'back', 'left', 'bottom'];
    $position = $position_names[$face_id] ?? 'unknown';

    return sprintf(
        '<div class="face-demo" data-face-id="%d" data-position="%s">
            <h2>%s</h2>
            <p>This is face %d (%s)</p>
            <p class="hint">Configure content in WordPress Customizer</p>
        </div>',
        $face_id,
        esc_attr($position),
        esc_html($label),
        $face_id,
        esc_html($position)
    );
}

/**
 * Get CSS for a face (backgrounds, etc.)
 *
 * @param int $face_id Face ID
 * @return string|null CSS string or null
 */
function gcube_get_face_css_for_bundle(int $face_id): ?string {
    // ONE surface computation for live render and bundle: gcube_face_surface
    // (css-output.php). The pre-schema version here re-implemented the
    // background logic with branches ('gradient'/'color') that were never
    // registered choices and a _bg_gradient setting that never existed.
    if (!function_exists('gcube_face_surface')) {
        return null;
    }

    $surface = gcube_face_surface($face_id + 1);
    $css_class = ['one', 'two', 'three', 'four', 'five', 'six'][$face_id];
    $css_parts = [];

    if (!empty($surface['front_bg'])) {
        $css_parts[] = ".{$css_class} { "
            . "--face-front-bg: {$surface['front_bg']}; "
            . "--face-front-bg-size: {$surface['front_bg_size']}; "
            . "--face-front-animation: {$surface['front_animation']}; }";
    }
    if (!empty($surface['back_bg'])) {
        $css_parts[] = ".{$css_class} { "
            . "--face-back-bg: {$surface['back_bg']}; "
            . "--face-back-bg-size: {$surface['back_bg_size']}; }";
    }

    return !empty($css_parts) ? implode("\n", $css_parts) : null;
}

/**
 * Get navigation menu data for bundle
 *
 * @return array Navigation structure
 */
function gcube_get_navigation_for_bundle(): array {
    $menu_items = [];
    $locations = get_nav_menu_locations();
    $menu_id = $locations['primary'] ?? 0;

    if ($menu_id) {
        $items = wp_get_nav_menu_items($menu_id);
        if ($items) {
            foreach ($items as $item) {
                if ($item->menu_item_parent == 0) {
                    $menu_items[] = [
                        'title' => $item->title,
                        'url' => $item->url,
                        'children' => [],
                    ];
                }
            }
        }
    }

    return [
        'menu' => $menu_items,
        'breadcrumbs' => [],
    ];
}

/**
 * Get recent posts for bundle
 *
 * @return array Posts structure
 */
function gcube_get_recent_posts_for_bundle(): array {
    $posts = get_posts([
        'post_type' => 'post',
        'post_status' => 'publish',
        'posts_per_page' => 10,
        'orderby' => 'date',
        'order' => 'DESC',
    ]);

    $list = [];
    foreach ($posts as $post) {
        $list[] = [
            'id' => (string) $post->ID,
            'title' => $post->post_title,
            'excerpt' => wp_trim_words($post->post_excerpt ?: $post->post_content, 20),
            'url' => get_permalink($post->ID),
            'date' => get_the_date('Y-m-d', $post),
        ];
    }

    $by_id = [];
    foreach ($list as $p) {
        $by_id[$p['id']] = $p;
    }

    return [
        'list' => $list,
        'by_id' => $by_id,
    ];
}

/**
 * Trigger bundle rebuild via invalidation event
 *
 * @param string $site_id Site identifier
 */
function gcube_trigger_bundle_rebuild(string $site_id): void {
    try {
        global $gCore;
        if (!$gCore) return;

        $gNodeClient = $gCore->getService('gnode_client');
        if (!$gNodeClient) return;

        // Publish invalidation event
        $channel = "{" . $site_id . "}:events:invalidate";
        $payload = json_encode([
            'event' => 'bundle_rebuild_requested',
            'site_id' => $site_id,
            'timestamp' => time(),
        ]);

        $storage = $gNodeClient->getStorage();
        if ($storage && method_exists($storage, 'publish')) {
            $storage->publish($channel, $payload);
            error_log("gCube: Triggered bundle rebuild for {$site_id}");
        }
    } catch (\Throwable $e) {
        error_log("gCube: Failed to trigger bundle rebuild: " . $e->getMessage());
    }
}

// Hook: Sync face mapping when customizer is saved
add_action('customize_save_after', 'gcube_sync_face_mapping_to_valkey');

// Hook: Sync face mapping when theme mods are updated
add_action('update_option_theme_mods_' . get_option('stylesheet'), function() {
    static $synced = false;
    if (!$synced) {
        $synced = true;
        gcube_sync_face_mapping_to_valkey();
    }
});

// Hook: Register .tera face templates on init (TTL-based, re-registers when expired)
//
// CLI invocations are explicitly excluded. wp-cli does not set is_admin(),
// wp_doing_cron(), or wp_doing_ajax(), so without the WP_CLI guard every
// `wp <anything>` re-entered the synchronous templateFragment loop — 13
// templates × ~5s poll timeout each whenever the daemon path is degraded.
// Template registration during ad-hoc CLI use is never the right entry
// point: Apache requests already populate templates on the warm path,
// and the dedicated `wp gtemplate register` command exists for explicit
// operator-driven re-registration.
add_action('init', function() {
    // Only on frontend requests, not admin/cron/ajax/cli (templates persist via TTL)
    if (is_admin() || wp_doing_cron() || wp_doing_ajax()) {
        return;
    }
    if (defined('WP_CLI') && WP_CLI) {
        return;
    }

    // Rate-limit: only check once per request, use transient to avoid per-pageload overhead
    if (get_transient('gcube_tera_templates_registered')) {
        return;
    }

    $gNode = gtemplate_gnode_keybased();
    if ($gNode) {
        // Mark BEFORE attempting: when the daemon path is degraded the
        // request dies mid-loop and a post-loop-only marker is never set,
        // so every subsequent pageload re-enters the synchronous poll.
        // 10-min retry window on failure, full hour after a clean pass.
        set_transient('gcube_tera_templates_registered', true, 600);
        gcube_register_templates_direct($gNode);
        set_transient('gcube_tera_templates_registered', true, 3600); // Re-check hourly
    }
}, 20);

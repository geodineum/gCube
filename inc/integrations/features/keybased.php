<?php
declare(strict_types=1);
/**
 * KeyBasedClient Integration for gCube
 *
 * Demonstrates how to use the new KeyBasedClient for 11× faster gNode operations.
 *
 * Performance comparison:
 * - Stream-based (old): 114ms average per request
 * - Key-based (new):     10ms average per request
 *
 * Usage:
 * 1. Ensure gNode daemon is running with key-based response handler
 * 2. Include this file: require_once get_template_directory() . '/inc/keybased-integration.php';
 * 3. Call functions: gcube_get_face_from_bundle(), gcube_render_template_fast(), etc.
 *
 * @package gCube
 * @version 2.0.0
 */

use gCore\gNode\gNodeClientInterface;
use gCore\gNode\Storage\ValKeyStorage;
use gCore\gNode\Exception\KeyBasedException;

/**
 * Get gNode Client instance from global variable
 *
 * Note: KeyBasedClient was deprecated - gNodeClient is now the unified client
 * that supports both key-based and stream-based operations.
 *
 * @return gNodeClientInterface|null
 */
function gcube_get_keybased_client(): ?gNodeClientInterface
{
    // Get from global variable (initialized in functions.php)
    if (isset($GLOBALS['gcube_gnode_keybased_client'])) {
        return $GLOBALS['gcube_gnode_keybased_client'];
    }

    error_log('[gCube KeyBased] gNode Client not initialized (missing global)');
    return null;
}

/**
 * Get stream-based gNode Client instance from global variable
 *
 * @return \gCore\gNode\Client|null
 */
function gcube_get_gnode_client(): ?\gCore\gNode\Client
{
    // Get from global variable (initialized in functions.php)
    if (isset($GLOBALS['gcube_gnode_client'])) {
        return $GLOBALS['gcube_gnode_client'];
    }

    error_log('[gCube gNode] Stream-based Client not initialized (missing global)');
    return null;
}

/**
 * Get face HTML from bundle (fastest method)
 *
 * Uses gNodeClient::getBundle() to retrieve the full site bundle,
 * then extracts the specific face HTML by face_id.
 *
 * Bundle key: {site_id}:gnode:bundle:full
 * Bundle structure: { faces: [{face_id, content_html, ...}, ...], ... }
 *
 * @param int $faceId Face ID (0-5)
 * @return string|null Face HTML or null if not available
 */
function gcube_get_face_from_bundle(int $faceId): ?string
{
    static $bundleCache = null;

    $client = gcube_get_keybased_client();
    if (!$client) {
        return null;
    }

    try {
        // Cache bundle within request to avoid multiple fetches
        if ($bundleCache === null) {
            $bundleCache = $client->getBundle();
        }

        if (!$bundleCache || !isset($bundleCache['faces'])) {
            return null;
        }

        // Find face by ID in bundle
        foreach ($bundleCache['faces'] as $face) {
            if (isset($face['face_id']) && (int)$face['face_id'] === $faceId) {
                return $face['content_html'] ?? null;
            }
        }

        return null;
    } catch (\Throwable $e) {
        error_log('[gCube] Failed to get face from bundle: ' . $e->getMessage());
        return null;
    }
}

/**
 * Get custom bundle by key
 *
 * Retrieves a custom bundle from ValKey using gNodeClient::getBundled().
 * Users can store any JSON-serializable data under custom bundle keys.
 *
 * Bundle key: {site_id}:gnode:bundle:{key}
 *
 * @param string $key Bundle key (e.g., 'products', 'portfolio', 'custom-faces')
 * @return array|null Bundle data or null if not available
 */
function gcube_get_custom_bundle(string $key): ?array
{
    $client = gcube_get_keybased_client();
    if (!$client) {
        return null;
    }

    try {
        return $client->getBundled($key);
    } catch (\Throwable $e) {
        error_log("[gCube] Failed to get custom bundle '{$key}': " . $e->getMessage());
        return null;
    }
}

/**
 * Store custom bundle by key
 *
 * Stores a custom bundle in ValKey for later retrieval with gcube_get_custom_bundle().
 * Useful for caching custom content like product catalogs, portfolios, or custom face content.
 *
 * Bundle key: {site_id}:gnode:bundle:{key}
 *
 * @param string $key Bundle key (e.g., 'products', 'portfolio', 'custom-faces')
 * @param array $data Bundle data (will be JSON-encoded)
 * @param int|null $ttl Time to live in seconds (null = no expiration)
 * @return bool True if stored successfully
 */
function gcube_set_custom_bundle(string $key, array $data, ?int $ttl = null): bool
{
    $client = gcube_get_keybased_client();
    if (!$client) {
        return false;
    }

    try {
        $siteId = gtemplate_get_site_id();
        $bundleKey = "{{$siteId}}:gnode:bundle:{$key}";
        $json = json_encode($data);

        if ($json === false) {
            error_log("[gCube] Failed to encode bundle '{$key}': " . json_last_error_msg());
            return false;
        }

        return $client->luaSet($bundleKey, $json, $ttl);
    } catch (\Throwable $e) {
        error_log("[gCube] Failed to store custom bundle '{$key}': " . $e->getMessage());
        return false;
    }
}

/**
 * Delete custom bundle by key
 *
 * @param string $key Bundle key
 * @return bool True if deleted
 */
function gcube_delete_custom_bundle(string $key): bool
{
    $client = gcube_get_keybased_client();
    if (!$client) {
        return false;
    }

    try {
        $siteId = gtemplate_get_site_id();
        $bundleKey = "{{$siteId}}:gnode:bundle:{$key}";
        return $client->luaDel($bundleKey);
    } catch (\Throwable $e) {
        error_log("[gCube] Failed to delete custom bundle '{$key}': " . $e->getMessage());
        return false;
    }
}

/**
 * Get entire bundle (all faces, posts, navigation, metadata)
 *
 * @return array|null Bundle data or null if not available
 */
function gcube_get_bundle(): ?array
{
    $client = gcube_get_keybased_client();
    if (!$client) {
        return null;
    }

    try {
        return $client->getBundle();
    } catch (\Throwable $e) {
        error_log('[gCube] Failed to get bundle: ' . $e->getMessage());
        return null;
    }
}

/**
 * Render template with automatic caching (key-based)
 *
 * @param string $templateId Template identifier
 * @param array $context Template context variables
 * @return string|null Rendered HTML or null on error
 */
function gcube_render_template_fast(string $templateId, array $context = []): ?string
{
    $client = gcube_get_keybased_client();
    if (!$client) {
        return null;
    }

    try {
        $response = $client->renderTemplate($templateId, $context);
        return $response['result'] ?? null;
    } catch (KeyBasedException $e) {
        error_log('[gCube KeyBased] Template render failed: ' . $e->getMessage());
        return null;
    }
}

/**
 * Invalidate cache for specific pattern
 *
 * @param string|null $pattern Key pattern (null = invalidate all site cache)
 * @return int Number of keys invalidated
 */
function gcube_invalidate_cache(?string $pattern = null): int
{
    $client = gcube_get_keybased_client();
    if (!$client) {
        return 0;
    }

    try {
        return $client->invalidateCache($pattern);
    } catch (KeyBasedException $e) {
        error_log('[gCube KeyBased] Cache invalidation failed: ' . $e->getMessage());
        return 0;
    }
}

/**
 * Invalidate bundle (forces rebuild by gNode daemon)
 *
 * @return bool True if bundle was invalidated
 */
function gcube_invalidate_bundle(): bool
{
    $client = gcube_get_keybased_client();
    if (!$client) {
        return false;
    }

    try {
        return $client->invalidateBundle();
    } catch (KeyBasedException $e) {
        error_log('[gCube KeyBased] Bundle invalidation failed: ' . $e->getMessage());
        return false;
    }
}

/**
 * Get cache statistics
 *
 * @return array Cache stats (key_count, total_size_mb, etc.)
 */
function gcube_get_cache_stats(): array
{
    $client = gcube_get_keybased_client();
    if (!$client) {
        return [];
    }

    try {
        return $client->getCacheStats();
    } catch (KeyBasedException $e) {
        error_log('[gCube KeyBased] Failed to get cache stats: ' . $e->getMessage());
        return [];
    }
}

/**
 * Get navigation menu from bundle
 *
 * @return array|null Navigation menu or null
 */
function gcube_get_navigation_from_bundle(): ?array
{
    $client = gcube_get_keybased_client();
    if (!$client) {
        return null;
    }

    try {
        return $client->getNavigationMenu();
    } catch (KeyBasedException $e) {
        error_log('[gCube KeyBased] Failed to get navigation: ' . $e->getMessage());
        return null;
    }
}

/**
 * Get posts list from bundle
 *
 * @return array|null Posts list or null
 */
function gcube_get_posts_from_bundle(): ?array
{
    $client = gcube_get_keybased_client();
    if (!$client) {
        return null;
    }

    try {
        return $client->getPostsList();
    } catch (KeyBasedException $e) {
        error_log('[gCube KeyBased] Failed to get posts: ' . $e->getMessage());
        return null;
    }
}

/**
 * Get site metadata from bundle
 *
 * @return array|null Site metadata or null
 */
function gcube_get_metadata_from_bundle(): ?array
{
    $client = gcube_get_keybased_client();
    if (!$client) {
        return null;
    }

    try {
        return $client->getSiteMetadata();
    } catch (KeyBasedException $e) {
        error_log('[gCube KeyBased] Failed to get metadata: ' . $e->getMessage());
        return null;
    }
}

/**
 * Hook: Invalidate cache when post is saved
 */
add_action('save_post', function($post_id) {
    // Skip autosaves and revisions
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
        return;
    }
    if (wp_is_post_revision($post_id)) {
        return;
    }

    // Invalidate bundle and cache
    gcube_invalidate_bundle();
    gcube_invalidate_cache('cache:*');

    error_log('[gCube KeyBased] Invalidated cache after post save: ' . $post_id);
}, 10, 1);

/**
 * Hook: Invalidate cache when theme options are updated
 */
add_action('update_option', function($option_name, $old_value, $value) {
    // Only invalidate for theme-related options
    if (strpos($option_name, 'gcube_') === 0 || strpos($option_name, 'theme_') === 0) {
        gcube_invalidate_bundle();
        gcube_invalidate_cache();
        error_log('[gCube KeyBased] Invalidated cache after option update: ' . $option_name);
    }
}, 10, 3);

/**
 * REST API endpoint: Get cache statistics
 */
add_action('rest_api_init', function() {
    register_rest_route('gcube/v1', '/cache/stats', [
        'methods' => 'GET',
        'callback' => function() {
            $stats = gcube_get_cache_stats();
            return new \WP_REST_Response($stats, 200);
        },
        'permission_callback' => function() {
            return current_user_can('manage_options');
        }
    ]);

    register_rest_route('gcube/v1', '/cache/invalidate', [
        'methods' => 'POST',
        'callback' => function(\WP_REST_Request $request) {
            $pattern = $request->get_param('pattern');
            $count = gcube_invalidate_cache($pattern);
            return new \WP_REST_Response([
                'invalidated' => $count,
                'pattern' => $pattern ?? 'all'
            ], 200);
        },
        'permission_callback' => function() {
            return current_user_can('manage_options');
        }
    ]);

    register_rest_route('gcube/v1', '/bundle/invalidate', [
        'methods' => 'POST',
        'callback' => function() {
            $success = gcube_invalidate_bundle();
            return new \WP_REST_Response([
                'success' => $success,
                'message' => $success ? 'Bundle invalidated' : 'Bundle invalidation failed'
            ], $success ? 200 : 500);
        },
        'permission_callback' => function() {
            return current_user_can('manage_options');
        }
    ]);
});

/**
 * Admin menu: Cache management
 */
add_action('admin_menu', function() {
    add_submenu_page(
        'gcore-dashboard',
        'Cache Stats',
        'Cache Stats',
        'manage_options',
        'gcube-cache',
        function() {
            $stats = gcube_get_cache_stats();
            ?>
            <div class="wrap gdash">
                <h1><?php echo esc_html__('gCube Cache Management', 'gcore'); ?></h1>

                <div class="gdash-card">
                    <div class="gdash-card-title"><?php echo esc_html__('Cache Statistics', 'gcore'); ?></div>
                    <?php if (!empty($stats)): ?>
                        <div class="gdash-stat-grid">
                            <div class="gdash-stat">
                                <div class="gdash-stat-label"><?php echo esc_html__('Site ID', 'gcore'); ?></div>
                                <div class="gdash-stat-value"><?php echo esc_html($stats['site_id']); ?></div>
                            </div>
                            <div class="gdash-stat">
                                <div class="gdash-stat-label"><?php echo esc_html__('Total Keys', 'gcore'); ?></div>
                                <div class="gdash-stat-value"><?php echo esc_html(number_format($stats['key_count'])); ?></div>
                            </div>
                            <div class="gdash-stat">
                                <div class="gdash-stat-label"><?php echo esc_html__('Total Size', 'gcore'); ?></div>
                                <div class="gdash-stat-value"><?php echo esc_html($stats['total_size_mb']); ?> <?php echo esc_html__('MB', 'gcore'); ?></div>
                            </div>
                        </div>
                    <?php else: ?>
                        <p><?php echo esc_html__('Cache statistics not available. KeyBasedClient may not be initialized.', 'gcore'); ?></p>
                    <?php endif; ?>
                </div>

                <div class="gdash-card">
                    <div class="gdash-card-title"><?php echo esc_html__('Actions', 'gcore'); ?></div>
                    <form method="post" action="">
                        <?php wp_nonce_field('gcube_cache_actions'); ?>
                        <p>
                            <button type="submit" name="action" value="invalidate_cache" class="button">
                                <?php echo esc_html__('Invalidate All Cache', 'gcore'); ?>
                            </button>
                            <button type="submit" name="action" value="invalidate_bundle" class="button">
                                <?php echo esc_html__('Invalidate Bundle', 'gcore'); ?>
                            </button>
                        </p>
                    </form>

                    <?php
                    if (isset($_POST['action']) && check_admin_referer('gcube_cache_actions')) {
                        if ($_POST['action'] === 'invalidate_cache') {
                            $count = gcube_invalidate_cache();
                            echo '<div class="notice notice-success"><p>' . esc_html(sprintf(__('Invalidated %d cache keys.', 'gcore'), $count)) . '</p></div>';
                        } elseif ($_POST['action'] === 'invalidate_bundle') {
                            $success = gcube_invalidate_bundle();
                            if ($success) {
                                echo '<div class="notice notice-success"><p>' . esc_html__('Bundle invalidated successfully.', 'gcore') . '</p></div>';
                            } else {
                                echo '<div class="notice notice-error"><p>' . esc_html__('Bundle invalidation failed.', 'gcore') . '</p></div>';
                            }
                        }
                    }
                    ?>
                </div>

                <div class="gdash-card">
                    <div class="gdash-card-title"><?php echo esc_html__('REST API Endpoints', 'gcore'); ?></div>
                    <ul>
                        <li><code>GET /wp-json/gcube/v1/cache/stats</code> - <?php echo esc_html__('Get cache statistics', 'gcore'); ?></li>
                        <li><code>POST /wp-json/gcube/v1/cache/invalidate</code> - <?php echo esc_html__('Invalidate cache (optional param: pattern)', 'gcore'); ?></li>
                        <li><code>POST /wp-json/gcube/v1/bundle/invalidate</code> - <?php echo esc_html__('Invalidate bundle', 'gcore'); ?></li>
                    </ul>
                </div>
            </div>
            <?php
        }
    );
});

/* ==========================================================================
   Bundle Chunking for Large Post Selections
   ========================================================================== */

/**
 * Maximum bundle size before chunking (512KB default)
 * ValKey typically handles 512KB-1MB per key efficiently
 */
define('GCUBE_MAX_BUNDLE_SIZE', 512 * 1024);

/**
 * Store bundle content with automatic chunking for large data
 *
 * When bundle content exceeds GCUBE_MAX_BUNDLE_SIZE, it's split into chunks
 * stored as separate keys: bundle:face:{id}:chunk:{n}
 *
 * @param int $face_id Face identifier
 * @param string $content Bundle content to store
 * @param int $ttl Time-to-live in seconds (default 3600)
 * @return bool Success status
 */
function gcube_store_face_bundle(int $face_id, string $content, int $ttl = 3600): bool
{
    $storage = $GLOBALS['gcube_gnode_storage'] ?? null;
    if (!$storage) {
        error_log('[gCube] Cannot store bundle: gNode storage not initialized');
        return false;
    }

    $site_id = gtemplate_get_site_id();
    $content_size = strlen($content);

    try {
        if ($content_size > GCUBE_MAX_BUNDLE_SIZE) {
            // Chunk the bundle
            $chunks = str_split($content, GCUBE_MAX_BUNDLE_SIZE);
            $chunk_count = count($chunks);

            foreach ($chunks as $i => $chunk) {
                $key = "{" . $site_id . "}:bundle:face:{$face_id}:chunk:{$i}";
                $storage->set($key, $chunk, $ttl);
            }

            // Store chunk metadata
            $meta_key = "{" . $site_id . "}:bundle:face:{$face_id}:meta";
            $storage->set($meta_key, json_encode([
                'chunked' => true,
                'chunk_count' => $chunk_count,
                'total_size' => $content_size,
                'created' => time(),
            ]), $ttl);

            error_log("[gCube] Stored chunked bundle for face {$face_id}: {$chunk_count} chunks, {$content_size} bytes");
            return true;
        } else {
            // Store as single key
            $key = "{" . $site_id . "}:bundle:face:{$face_id}";
            $storage->set($key, $content, $ttl);

            // Clear any existing chunk metadata
            $meta_key = "{" . $site_id . "}:bundle:face:{$face_id}:meta";
            $storage->del($meta_key);

            return true;
        }
    } catch (\Throwable $e) {
        error_log('[gCube] Failed to store face bundle: ' . $e->getMessage());
        return false;
    }
}

/**
 * Retrieve face bundle with automatic chunk reassembly
 *
 * Checks for chunked bundles first, then falls back to single-key retrieval.
 *
 * @param int $face_id Face identifier
 * @return string|null Bundle content or null if not found
 */
function gcube_retrieve_face_bundle(int $face_id): ?string
{
    $storage = $GLOBALS['gcube_gnode_storage'] ?? null;
    if (!$storage) {
        return null;
    }

    $site_id = gtemplate_get_site_id();

    try {
        // Check for chunk metadata first
        $meta_key = "{" . $site_id . "}:bundle:face:{$face_id}:meta";
        $meta_raw = $storage->get($meta_key);

        if ($meta_raw) {
            $meta = json_decode($meta_raw, true);
            if ($meta && !empty($meta['chunked']) && !empty($meta['chunk_count'])) {
                // Reassemble chunked bundle
                $content = '';
                for ($i = 0; $i < $meta['chunk_count']; $i++) {
                    $chunk_key = "{" . $site_id . "}:bundle:face:{$face_id}:chunk:{$i}";
                    $chunk = $storage->get($chunk_key);
                    if ($chunk === null) {
                        error_log("[gCube] Missing chunk {$i} for face {$face_id}");
                        return null; // Incomplete bundle
                    }
                    $content .= $chunk;
                }
                return $content;
            }
        }

        // Fall back to single-key retrieval
        $key = "{" . $site_id . "}:bundle:face:{$face_id}";
        return $storage->get($key);

    } catch (\Throwable $e) {
        error_log('[gCube] Failed to retrieve face bundle: ' . $e->getMessage());
        return null;
    }
}

/**
 * Invalidate chunked bundle for a face
 *
 * Removes all chunk keys and metadata for a face bundle.
 *
 * @param int $face_id Face identifier
 * @return bool Success status
 */
function gcube_invalidate_face_bundle(int $face_id): bool
{
    $storage = $GLOBALS['gcube_gnode_storage'] ?? null;
    if (!$storage) {
        return false;
    }

    $site_id = gtemplate_get_site_id();

    try {
        // Check for chunked bundle
        $meta_key = "{" . $site_id . "}:bundle:face:{$face_id}:meta";
        $meta_raw = $storage->get($meta_key);

        if ($meta_raw) {
            $meta = json_decode($meta_raw, true);
            if ($meta && !empty($meta['chunk_count'])) {
                // Delete all chunks
                for ($i = 0; $i < $meta['chunk_count']; $i++) {
                    $chunk_key = "{" . $site_id . "}:bundle:face:{$face_id}:chunk:{$i}";
                    $storage->del($chunk_key);
                }
            }
            // Delete metadata
            $storage->del($meta_key);
        }

        // Delete single-key bundle (if exists)
        $key = "{" . $site_id . "}:bundle:face:{$face_id}";
        $storage->del($key);

        return true;

    } catch (\Throwable $e) {
        error_log('[gCube] Failed to invalidate face bundle: ' . $e->getMessage());
        return false;
    }
}

/**
 * Hook: Invalidate posts-related bundles when posts change
 *
 * When a post is saved, published, or deleted, invalidate any face bundles
 * that use the 'posts' content source.
 */
add_action('save_post', function($post_id, $post, $update) {
    // Skip autosaves and revisions
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
        return;
    }
    if (wp_is_post_revision($post_id)) {
        return;
    }
    if ($post->post_type !== 'post') {
        return;
    }

    // Find faces using 'posts' source and invalidate their bundles
    for ($i = 1; $i <= 6; $i++) {
        $source = gcube_mod("cube_face_{$i}_source");
        if ($source === 'posts') {
            $face_id = $i - 1;
            gcube_invalidate_face_bundle($face_id);
            error_log("[gCube] Invalidated posts bundle for face {$face_id} after post {$post_id} change");
        }
    }
}, 10, 3);

/**
 * Hook: Invalidate bundles when category changes
 */
add_action('edited_category', function($term_id) {
    // Find faces using 'posts' source with category filters that include this category
    for ($i = 1; $i <= 6; $i++) {
        $source = gcube_mod("cube_face_{$i}_source");
        if ($source === 'posts') {
            $filter = gcube_mod("cube_face_{$i}_category_filter");
            if (empty($filter) || strpos($filter, (string)$term_id) !== false) {
                $face_id = $i - 1;
                gcube_invalidate_face_bundle($face_id);
                error_log("[gCube] Invalidated posts bundle for face {$face_id} after category {$term_id} change");
            }
        }
    }
});

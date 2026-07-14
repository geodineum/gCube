<?php
declare(strict_types=1);
/**
 * Bundle Manifest Integration
 *
 * Syncs WordPress nav menu structure into gNode asset manifests so the daemon's
 * background builder can pre-assemble content bundles for instant key-based retrieval.
 *
 * Flow: Nav menu change → manifest sync → daemon builds bundle → getBundled() ~1ms
 *
 * @package    gCube
 * @subpackage Integrations/Features
 * @since      2.1.0
 *
 * @dependencies
 *   - gNode-Client with manifestSet/assetStore methods
 *   - gnode_asset.lua loaded in ValKey
 *   - content-sync.php (face rendering functions)
 */

if (!defined('ABSPATH')) {
    exit;
}

// ============================================================================
// NAV MENU → MANIFEST SYNC
// ============================================================================

/**
 * Sync the primary navigation menu to gNode manifests
 *
 * Each top-level nav item becomes a manifest. The manifest's slots reference
 * assets (rendered page/post content) stored in ValKey. The daemon's background
 * builder reads manifests and assembles compressed bundles at:
 *   {site_id}:gnode:bundle:{manifest_id}
 *
 * @return array Summary of synced manifests
 */
function gcube_sync_nav_to_manifests(): array
{
    $gNode = gtemplate_gnode_keybased();
    if (!$gNode) {
        return ['error' => 'gNode client unavailable'];
    }

    $locations = get_nav_menu_locations();
    $menu_id = $locations['primary'] ?? 0;

    if (!$menu_id) {
        return ['error' => 'No primary menu set'];
    }

    $items = wp_get_nav_menu_items($menu_id);
    if (!$items || !is_array($items)) {
        return ['error' => 'Primary menu has no items'];
    }

    $results = [];

    foreach ($items as $item) {
        // Only top-level nav items (not children)
        if ((int) $item->menu_item_parent !== 0) {
            continue;
        }

        try {
            $manifest_id = gcube_nav_item_to_manifest_id($item);
            $manifest = gcube_build_manifest_from_nav_item($item, $items);

            // Store the linked content as an asset
            $asset_id = gcube_store_nav_content_as_asset($item, $gNode);

            if ($asset_id) {
                $manifest['slots'][] = [
                    'id' => 'content',
                    'asset_key' => $asset_id,
                    'type' => 'html'
                ];
            }

            $result = $gNode->manifestSet($manifest_id, $manifest);
            $results[$manifest_id] = ['ok' => true, 'asset' => $asset_id];
        } catch (\Throwable $e) {
            error_log("gCube: Failed to sync manifest for nav item '{$item->title}': " . $e->getMessage());
            $results[$manifest_id ?? $item->title] = ['ok' => false, 'error' => $e->getMessage()];
        }
    }

    return $results;
}

/**
 * Convert a nav menu item to a manifest ID slug
 *
 * @param WP_Post $item Nav menu item
 * @return string Manifest ID (e.g., 'nav:about', 'nav:contact')
 */
function gcube_nav_item_to_manifest_id($item): string
{
    // Use URL slug if linking to a page, otherwise sanitize the title
    if ($item->type === 'post_type' && $item->object_id) {
        $post = get_post($item->object_id);
        if ($post) {
            return 'nav:' . $post->post_name;
        }
    }

    return 'nav:' . sanitize_title($item->title);
}

/**
 * Build a manifest definition from a nav menu item
 *
 * @param WP_Post $item Nav menu item
 * @param array $all_items All menu items (for finding children)
 * @return array Manifest definition
 */
function gcube_build_manifest_from_nav_item($item, array $all_items): array
{
    // Find child items for this nav button
    $children = [];
    foreach ($all_items as $child) {
        if ((int) $child->menu_item_parent === (int) $item->ID) {
            $children[] = [
                'title' => $child->title,
                'url' => $child->url,
                'type' => $child->type,
                'object_id' => $child->object_id ?? null
            ];
        }
    }

    return [
        'layout' => 'page',
        'type' => 'inline',
        'version' => '1.0.0',
        'slots' => [], // Populated by caller with asset references
        'sections' => [
            'navigation' => [
                'title' => $item->title,
                'url' => $item->url,
                'children' => $children
            ],
            'meta' => [
                'post_type' => $item->type,
                'object_id' => $item->object_id ?? null,
                'nav_item_id' => $item->ID
            ]
        ],
        'build_options' => [
            'compress' => true,
            'ttl' => 86400 // 24 hours
        ]
    ];
}

/**
 * Render nav item's linked content and store as an asset
 *
 * @param WP_Post $item Nav menu item
 * @param object $gNode gNode client
 * @return string|null Asset ID or null if content couldn't be rendered
 */
function gcube_store_nav_content_as_asset($item, $gNode): ?string
{
    $html = null;
    $asset_id = null;

    if ($item->type === 'post_type' && $item->object_id) {
        $post = get_post((int) $item->object_id);

        if ($post && $post->post_status === 'publish') {
            $asset_id = 'wp_' . $post->post_type . '_' . $post->ID;

            // Render the post content
            $html = apply_filters('the_content', $post->post_content);

            // Wrap with title if applicable
            $title = $post->post_title;
            if ($title) {
                $html = '<h1 class="entry-title">' . esc_html($title) . '</h1>' . $html;
            }
        }
    } elseif ($item->type === 'taxonomy' && $item->object_id) {
        $term = get_term((int) $item->object_id);
        if ($term && !is_wp_error($term)) {
            $asset_id = 'wp_term_' . $term->term_id;
            $html = '<h1 class="archive-title">' . esc_html($term->name) . '</h1>';
            if ($term->description) {
                $html .= '<div class="archive-description">' . wp_kses_post($term->description) . '</div>';
            }
        }
    }

    if ($asset_id && $html) {
        try {
            $gNode->assetStore($asset_id, $html, 'text/html', 86400);
            return $asset_id;
        } catch (\Throwable $e) {
            error_log("gCube: Failed to store asset '{$asset_id}': " . $e->getMessage());
        }
    }

    return null;
}

// ============================================================================
// WORDPRESS HOOKS
// ============================================================================

/**
 * Nav menu changed — schedule manifest resync
 */
function gcube_on_nav_menu_change(int $menu_id): void
{
    $locations = get_nav_menu_locations();
    $primary_id = $locations['primary'] ?? 0;

    // Only react to primary menu changes
    if ($menu_id !== $primary_id && $primary_id !== 0) {
        return;
    }

    // Defer to avoid blocking the admin save
    if (!wp_next_scheduled('gcube_sync_nav_manifests_event')) {
        wp_schedule_single_event(time() + 5, 'gcube_sync_nav_manifests_event');
    }
}

/**
 * Post/page saved — update its asset if referenced by a manifest
 */
function gcube_on_post_save_update_asset(int $post_id, $post, bool $update): void
{
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
        return;
    }
    if (wp_is_post_revision($post_id) || $post->post_status !== 'publish') {
        return;
    }

    $gNode = gtemplate_gnode_keybased();
    if (!$gNode) {
        return;
    }

    // Re-store the asset
    $asset_id = 'wp_' . $post->post_type . '_' . $post_id;
    $html = apply_filters('the_content', $post->post_content);
    $title = $post->post_title;
    if ($title) {
        $html = '<h1 class="entry-title">' . esc_html($title) . '</h1>' . $html;
    }

    try {
        $gNode->assetStore($asset_id, $html, 'text/html', 86400);

        // Signal daemon to rebuild any bundles referencing this asset
        $gNode->invalidateBundle();
    } catch (\Throwable $e) {
        error_log("gCube: Failed to update asset for post {$post_id}: " . $e->getMessage());
    }
}

// Register hooks
add_action('wp_update_nav_menu', 'gcube_on_nav_menu_change', 10, 1);
add_action('save_post', 'gcube_on_post_save_update_asset', 15, 3);
add_action('customize_save_after', function() {
    if (!wp_next_scheduled('gcube_sync_nav_manifests_event')) {
        wp_schedule_single_event(time() + 5, 'gcube_sync_nav_manifests_event');
    }
});

// Deferred event handler
add_action('gcube_sync_nav_manifests_event', 'gcube_sync_nav_to_manifests');

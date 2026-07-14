<?php
declare(strict_types=1);
/**
 * REST API: Sync Resources
 *
 * ValKey synchronization endpoints and hooks.
 *
 * @package    gCube
 * @subpackage REST\Resources
 * @since      2.0.0
 *
 * ENDPOINTS:
 * ==========
 * POST /gcube/v1/sync-face-mapping  - Sync face mapping to ValKey
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Register sync REST routes
 */
function gcube_register_sync_routes() {
    register_rest_route('gcube/v1', '/sync-face-mapping', [
        'methods' => 'POST',
        'callback' => 'gcube_rest_sync_face_mapping',
        'permission_callback' => function() {
            return current_user_can('manage_options');
        },
    ]);
}

/**
 * POST /gcube/v1/sync-face-mapping
 *
 * Trigger face mapping sync for gNode bundle builder.
 *
 * @param WP_REST_Request $request
 * @return WP_REST_Response
 */
function gcube_rest_sync_face_mapping($request) {
    if (!function_exists('gcube_sync_face_mapping_to_valkey')) {
        require_once get_stylesheet_directory() . '/inc/rendering/content-sync.php';
    }

    $result = gcube_sync_face_mapping_to_valkey();

    if ($result) {
        return new WP_REST_Response([
            'success' => true,
            'message' => 'Face mapping synced to ValKey successfully',
            'site_id' => gtemplate_get_site_id(),
        ], 200);
    }

    return new WP_REST_Response([
        'success' => false,
        'message' => 'Failed to sync face mapping - check error logs',
    ], 500);
}

/**
 * Auto-sync face mapping on init (once per hour)
 */
add_action('init', function() {
    if (is_admin() && !wp_doing_ajax()) {
        return;
    }

    $transient_key = 'gcube_face_mapping_sync_' . gtemplate_get_site_id();
    if (get_transient($transient_key)) {
        return;
    }

    set_transient($transient_key, time(), HOUR_IN_SECONDS);

    if (!wp_next_scheduled('gcube_async_face_mapping_sync')) {
        wp_schedule_single_event(time() + 1, 'gcube_async_face_mapping_sync');
    }
});

/**
 * Hook: Async face mapping sync
 */
add_action('gcube_async_face_mapping_sync', function() {
    if (function_exists('gcube_sync_face_mapping_to_valkey')) {
        gcube_sync_face_mapping_to_valkey();
    }
});

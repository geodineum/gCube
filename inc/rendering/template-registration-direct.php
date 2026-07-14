<?php
declare(strict_types=1);
/**
 * Direct gNode Template Registration (Fallback)
 *
 * Used if TemplateRenderer is not available
 *
 * @package gCube
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Register templates directly with gNode-Client (fallback method)
 *
 * @param object $gNode gNode-Client instance
 */
function gcube_register_templates_direct($gNode) {
    error_log('gCube: Using DIRECT gNode template registration (fallback)');

    $template_dir = get_stylesheet_directory() . '/templates';

    // Template categories
    $template_types = [
        'faces' => ['home', 'blog-list', 'single-post', 'contact-form', 'gallery', 'dashboard'],
        'fragments' => ['post-card', 'product-card', 'user-widget', 'notification-toast'],
        'components' => ['header', 'footer', 'sidebar']
    ];

    $registered_count = 0;
    $failed_count = 0;

    foreach ($template_types as $type => $templates) {
        foreach ($templates as $template_name) {
            $path = "{$template_dir}/{$type}/{$template_name}.tera";

            if (!file_exists($path)) {
                error_log("gCube WARNING: Template not found: {$path}");
                $failed_count++;
                continue;
            }

            $content = file_get_contents($path);

            try {
                // Direct gNode registration
                // Signature: templateFragment(string $templateId, string $content, array $dependencies = [])
                $result = $gNode->templateFragment(
                    $template_name,  // template ID
                    $content,        // template content
                    []               // dependencies (empty array)
                );

                if ($result) {
                    error_log("gCube: ✅ Registered '{$template_name}' ({$type}) via direct gNode");
                    $registered_count++;
                } else {
                    error_log("gCube WARNING: ❌ Failed to register '{$template_name}' ({$type})");
                    $failed_count++;
                }
            } catch (\Throwable $e) {
                error_log("gCube ERROR: Exception registering '{$template_name}': " . $e->getMessage());
                $failed_count++;
            }
        }
    }

    error_log("gCube: Direct registration complete - {$registered_count} registered, {$failed_count} failed");
}

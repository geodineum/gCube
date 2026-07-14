<?php
declare(strict_types=1);
/**
 * gCube Theme Constants
 *
 * Defines all theme constants used throughout the codebase.
 * This file MUST be loaded first, before any other theme files.
 *
 * @package    gCube
 * @subpackage Bootstrap
 * @since      2.0.0
 *
 * @dependencies None (first file loaded)
 * @dependents   All theme files
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Theme version - used for cache busting and compatibility checks
 */
if (!defined('GCUBE_VERSION')) {
    define('GCUBE_VERSION', '1.0.0');
}

/**
 * Debug mode - enables verbose logging
 * Set to true only in development environments
 *
 * @var bool
 */
if (!defined('GCUBE_DEBUG')) {
    define('GCUBE_DEBUG', false);
}

/**
 * Free-tier mode - runs without gNode/ValKey infrastructure
 *
 * When enabled:
 * - Uses WordPress transients instead of ValKey
 * - Falls back to PHP rendering instead of gNode templates
 * - No bundle generation or caching
 *
 * Set to true in wp-config.php for free-tier deployments:
 *   define('GCUBE_FREE_TIER', true);
 *
 * @var bool
 */
if (!defined('GCUBE_FREE_TIER')) {
    define('GCUBE_FREE_TIER', false);
}

/**
 * Theme directory path (cached for performance)
 */
if (!defined('GCUBE_DIR')) {
    define('GCUBE_DIR', get_stylesheet_directory());
}

/**
 * Theme directory URI (cached for performance)
 */
if (!defined('GCUBE_URI')) {
    define('GCUBE_URI', get_stylesheet_directory_uri());
}

/**
 * Inc directory path (most includes live here)
 */
if (!defined('GCUBE_INC_DIR')) {
    define('GCUBE_INC_DIR', GCUBE_DIR . '/inc');
}

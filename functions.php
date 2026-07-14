<?php
/**
 * gCube Theme Functions
 *
 * Clean entry point for the gCube 3D cube WordPress theme.
 * All functionality is organized into modular components under inc/.
 *
 * @package    gCube
 * @version    1.0.0
 * @author     Geodineum
 * @license    GPL-2.0+
 *
 * ARCHITECTURE OVERVIEW:
 * ======================
 *
 * gCube is a CHILD theme of gTemplate (`Template: gtemplate` in style.css).
 * The parent theme provides the ecosystem-wide infrastructure — config
 * loaders, REST routes, environment gate, performance tweaks, security
 * hardening, integrations, managers, the face-renderer + most content
 * sources, customizer base, etc. This file + inc/ contain ONLY the
 * cube-specific overrides on top of that foundation.
 *
 * Cube-specific surface (everything in inc/):
 *
 *   inc/
 *   ├── bootstrap/
 *   │   ├── constants.php          # GCUBE_VERSION / GCUBE_DIR / GCUBE_INC_DIR
 *   │   ├── autoload.php           # Slim loader for cube-only files
 *   │   └── gNodeConfigLoader.php  # \gCube\gNodeConfigLoader → \gTemplate alias
 *   │
 *   ├── assets/
 *   │   ├── enqueue.php            # Cube asset enqueue (cube-3d JS, faces CSS)
 *   │   └── pwa-rewrite.php        # PWA manifest + service worker rewriting
 *   │
 *   ├── rendering/
 *   │   ├── content-sources/glass.php       # Glass-mode (transparent face)
 *   │   ├── content-sync.php                # Page → cube-face sync
 *   │   └── template-registration-direct.php # Direct Tera template registration
 *   │
 *   ├── integrations/
 *   │   ├── features/bundle-manifest.php
 *   │   └── features/keybased.php
 *   │
 *   ├── rest/resources/sync.php    # `wp-json/gcube/v1/sync-face-mapping`
 *   ├── admin/analytics.php        # wp-admin cube-usage dashboard
 *   ├── cli/class-gcube-cli.php    # `wp gcube viewkey` (cube-only CLI)
 *   └── customizer/                # Cube settings, faces, navigation, PWA, colors
 *
 * RENDERING TIERS (parent-provided face-renderer; child supplies face data):
 * 1. BUNDLE: Pre-rendered HTML from ValKey (~1-5ms)
 * 2. gNode: Tera template via gNode daemon (~10ms)
 * 3. PHP: WordPress fallback rendering (~50-100ms)
 *
 * MIGRATED (Ch.1.A pre-launch cleanup, ~14k LOC duplicate code removed):
 *   wp gcube register|status|deregister|refresh|capability  →  wp gtemplate <same>
 *   wp gcube config|sync-config|runtime-{get,set,list}      →  wp gtemplate <same>
 *   wp gcube aio-*                                          →  wp gtemplate aio-*
 *
 * @see inc/bootstrap/autoload.php  Cube-unique file load order
 * @see CLAUDE.md  Architecture documentation
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// ============================================================================
// BOOTSTRAP
// ============================================================================

// Load constants first (defines GCUBE_DIR, GCUBE_INC_DIR, etc.)
require_once get_stylesheet_directory() . '/inc/bootstrap/constants.php';

// Load autoloader (handles everything else in correct order)
require_once GCUBE_INC_DIR . '/bootstrap/autoload.php';

// ============================================================================
// INITIALIZATION COMPLETE
// ============================================================================

if (GCUBE_DEBUG) {
    error_log('[gCube] Theme loaded successfully (v' . GCUBE_VERSION . ')');
}

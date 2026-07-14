<?php
declare(strict_types=1);
/**
 * gCube Autoloader & File Loader
 *
 * gCube is a CHILD theme of gTemplate. The parent theme provides the
 * ecosystem-wide infrastructure (config loaders, REST routes, integrations,
 * managers, performance tweaks, environment gate, security hardening, etc.)
 * via its own autoload.php which WordPress runs after this one.
 *
 * Therefore this file only loads files that are GENUINELY cube-specific —
 * i.e., files that exist in `gCube/inc/` but NOT under the same path in
 * `gTemplate/inc/`. Anything that used to live in both as a prefix-rename
 * duplicate (~14k LOC) was removed in the Ch.1.A pre-launch cleanup.
 *
 * If you're tempted to add a require_once for something that could live in
 * the parent theme — don't. Add it to `gTemplate/inc/...` instead and call
 * the `gtemplate_*` function from cube-unique code.
 *
 * @package    gCube
 * @subpackage Bootstrap
 * @since      2.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

//-----------------------------------------------------------------------------
// Phase 1: Composer Autoloader (gCube ships its own vendor/ — pinned subset of
// gCore + gNode-Client deps so the child theme can run standalone in tests).
//-----------------------------------------------------------------------------

$composer_autoload = GCUBE_DIR . '/vendor/autoload.php';
if (file_exists($composer_autoload)) {
    require_once $composer_autoload;
} elseif (GCUBE_DEBUG) {
    error_log('[gCube] Composer autoloader not found at ' . $composer_autoload . ' — degraded mode');
}

//-----------------------------------------------------------------------------
// Phase 2: Namespace alias for gNodeConfigLoader so legacy
// `\gCube\gNodeConfigLoader::get(...)` callers continue to resolve. The real
// class is `\gTemplate\gNodeConfigLoader` in the parent theme.
//-----------------------------------------------------------------------------

require_once GCUBE_INC_DIR . '/bootstrap/gNodeConfigLoader.php';

//-----------------------------------------------------------------------------
// Phase 3: Cube-specific assets (cube manifest + service worker, asset enqueue)
//-----------------------------------------------------------------------------

require_once GCUBE_INC_DIR . '/assets/enqueue.php';
require_once GCUBE_INC_DIR . '/assets/pwa-rewrite.php';

//-----------------------------------------------------------------------------
// Phase 4: Cube-specific rendering (glass-mode content source, content sync,
// direct template registration). Other content sources + face renderer come
// from the parent.
//-----------------------------------------------------------------------------

require_once GCUBE_INC_DIR . '/rendering/content-sources/glass.php';
require_once GCUBE_INC_DIR . '/rendering/content-sync.php';
require_once GCUBE_INC_DIR . '/rendering/template-registration-direct.php';

//-----------------------------------------------------------------------------
// Phase 5: Cube-specific integrations (bundle manifest
// for cube faces, key-based bundle/face cache).
//-----------------------------------------------------------------------------

require_once GCUBE_INC_DIR . '/integrations/features/bundle-manifest.php';
require_once GCUBE_INC_DIR . '/integrations/features/keybased.php';

//-----------------------------------------------------------------------------
// Phase 6: Cube-specific REST resource (face-mapping sync). Other REST
// resources come from the parent's rest/index.php.
//-----------------------------------------------------------------------------

require_once GCUBE_INC_DIR . '/rest/resources/sync.php';

//-----------------------------------------------------------------------------
// Phase 7: Admin (cube usage analytics dashboard) — wp-admin only.
//-----------------------------------------------------------------------------

if (is_admin()) {
    require_once GCUBE_INC_DIR . '/admin/analytics.php';
}

//-----------------------------------------------------------------------------
// Phase 8: WP-CLI (cube-specific commands; ecosystem commands live in the
// parent and are exposed via `wp gtemplate ...`).
//-----------------------------------------------------------------------------

if (defined('WP_CLI') && WP_CLI) {
    require_once GCUBE_INC_DIR . '/cli/class-gcube-cli.php';
}

//-----------------------------------------------------------------------------
// Phase 9: Customizer. schema.php is the canonical setting-id/default map
// every consumer resolves through; sanitizers come from parent gTemplate.
//-----------------------------------------------------------------------------

require_once GCUBE_INC_DIR . '/customizer/schema.php';
require_once GCUBE_INC_DIR . '/customizer/register.php';
require_once GCUBE_INC_DIR . '/customizer/css-output.php';

//-----------------------------------------------------------------------------
// Initialization complete.
//-----------------------------------------------------------------------------

if (GCUBE_DEBUG) {
    error_log('[gCube] Autoload complete (child-theme mode; parent gTemplate provides ecosystem-wide infrastructure)');
}

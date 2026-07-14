<?php
declare(strict_types=1);
/**
 * PWA Rewrite Rules for gCube
 *
 * Serves sw.js and manifest.json from WordPress root URLs.
 * Service workers MUST be served from root to control the entire site scope.
 *
 * @package gCube
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Add rewrite rules for PWA files
 *
 * This allows:
 * - /sw.js → Serves service worker with correct Content-Type
 * - /manifest.json → Serves manifest with correct Content-Type
 */
// PWA asset paths that must NEVER get a trailing slash added by
// WP's redirect_canonical(). The permalink_structure of '/%postname%/'
// makes WP try to canonicalize /sw.js -> /sw.js/, which (a) breaks the
// rewrite rule (anchored to no-slash) and (b) re-enters the gate's
// template_redirect path. Returning false from this filter aborts the
// canonical redirect entirely for these paths.
add_filter('redirect_canonical', function($redirect_url, $requested_url) {
    $path = parse_url($requested_url, PHP_URL_PATH);
    if (in_array($path, ['/sw.js', '/manifest.json', '/manifest.webmanifest'], true)) {
        return false;
    }
    return $redirect_url;
}, 10, 2);

add_action('init', function() {
    // Rewrite /sw.js to our handler
    add_rewrite_rule(
        '^sw\.js$',
        'index.php?gcube_pwa_file=sw',
        'top'
    );

    // Rewrite /manifest.json to our handler
    add_rewrite_rule(
        '^manifest\.json$',
        'index.php?gcube_pwa_file=manifest',
        'top'
    );

    // Also handle /manifest.webmanifest (some browsers prefer this)
    add_rewrite_rule(
        '^manifest\.webmanifest$',
        'index.php?gcube_pwa_file=manifest',
        'top'
    );
});

/**
 * Register query var for PWA file routing
 */
add_filter('query_vars', function($vars) {
    $vars[] = 'gcube_pwa_file';
    return $vars;
});

/**
 * Handle PWA file requests
 */
add_action('template_redirect', function() {
    $pwa_file = get_query_var('gcube_pwa_file');

    if (!$pwa_file) {
        return;
    }

    if (!gcube_pwa_enabled()) {
        // PWA is off: retire any previously-registered worker by serving a
        // self-unregistering sw.js (registered workers poll this URL for
        // updates, so this cleanly uninstalls them); the manifest 404s.
        if ($pwa_file === 'sw') {
            gcube_serve_unregister_worker();
        } else {
            status_header(404);
            nocache_headers();
            exit;
        }
        return;
    }

    switch ($pwa_file) {
        case 'sw':
            gcube_serve_service_worker();
            break;

        case 'manifest':
            gcube_serve_manifest();
            break;
    }
});

/**
 * PWA master switch. Requires BOTH the Chapter-2 manifest extension and the
 * operator opt-in (`enable_pwa`, default off); PWA is a Chapter-2 capability,
 * so base tier emits nothing even if the toggle was previously set.
 */
function gcube_pwa_enabled(): bool {
    return gcube_manifest_available() && (bool) gcube_mod('enable_pwa');
}

/**
 * Whether a REAL manifest backend is available (not the inert base-tier stub).
 *
 * ManifestManager exposes no isAvailable(), so the stub self-identifies through
 * getStatus()['stub_mode']: true = inert Ch.1 stub, false = the Chapter-2
 * gcore-manifest extension. Under the stub, getManifestData() returns minimal
 * placeholder data (empty icons + stub_mode/upgrade_message keys) that would
 * override gCube's own richer native manifest, so the serve path must ignore it.
 */
function gcube_manifest_available(): bool {
    global $gCore;

    if (!$gCore) {
        return false;
    }

    try {
        $manifest = $gCore->getService('ManifestManager');
    } catch (\Throwable $e) {
        return false;
    }

    if (!$manifest || !method_exists($manifest, 'getStatus')) {
        return false;
    }

    try {
        $status = $manifest->getStatus();
    } catch (\Throwable $e) {
        return false;
    }

    return is_array($status) && empty($status['stub_mode']);
}

/**
 * Serve a minimal service worker whose only job is to unregister itself.
 * Sent in place of sw.js while PWA is disabled so visitors who installed
 * the worker earlier are cleanly retired on their next update check.
 */
function gcube_serve_unregister_worker(): void {
    header('Content-Type: application/javascript; charset=utf-8');
    header('Service-Worker-Allowed: /');
    header('Cache-Control: no-cache, must-revalidate');
    echo "self.addEventListener('install',function(){self.skipWaiting();});\n";
    echo "self.addEventListener('activate',function(){self.registration.unregister().then(function(){return self.clients.matchAll();}).then(function(cs){cs.forEach(function(c){c.navigate(c.url);});});});\n";
    exit;
}

/**
 * Serve the service worker with correct headers
 */
function gcube_serve_service_worker(): void {
    // Find the service worker file
    $sw_paths = [
        get_stylesheet_directory() . '/sw.js',
        get_stylesheet_directory() . '/assets/js/sw.js',
        get_stylesheet_directory() . '/assets/js/service-worker.js',
    ];

    $sw_content = null;
    foreach ($sw_paths as $path) {
        if (file_exists($path)) {
            $sw_content = file_get_contents($path);
            break;
        }
    }

    if (!$sw_content) {
        // Generate a minimal service worker if file not found
        $sw_content = gcube_generate_minimal_sw();
    }

    // Set headers for service worker
    header('Content-Type: application/javascript; charset=utf-8');
    header('Service-Worker-Allowed: /');
    header('Cache-Control: no-cache, no-store, must-revalidate');
    header('X-Content-Type-Options: nosniff');

    echo $sw_content;
    exit;
}

/**
 * Serve the manifest.json with correct headers
 */
function gcube_serve_manifest(): void {
    // Try to get from ManifestManager first
    global $gCore;

    $manifest_data = null;

    // Only defer to ManifestManager when the REAL extension is present. The
    // base-tier stub returns minimal placeholder data (empty icons +
    // stub_mode/upgrade_message keys) which would short-circuit and leak into
    // gCube's own native manifest, so under the stub we fall through below.
    if ($gCore && gcube_manifest_available()) {
        try {
            $manifest = $gCore->getService('ManifestManager');
            if ($manifest && method_exists($manifest, 'getManifestData')) {
                $manifest_data = $manifest->getManifestData();
            }
        } catch (\Throwable $e) {
            // Fall through to file-based
        }
    }

    // Try static file
    if (!$manifest_data) {
        $manifest_path = get_stylesheet_directory() . '/manifest.json';
        if (file_exists($manifest_path)) {
            $manifest_data = json_decode(file_get_contents($manifest_path), true);
        }
    }

    // Generate dynamically if still no manifest
    if (!$manifest_data) {
        $manifest_data = gcube_generate_manifest();
    }

    // Customize with site-specific values
    $manifest_data = gcube_customize_manifest($manifest_data);

    // Set headers
    header('Content-Type: application/manifest+json; charset=utf-8');
    header('Cache-Control: public, max-age=86400');
    header('X-Content-Type-Options: nosniff');

    echo json_encode($manifest_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    exit;
}

/**
 * Customize manifest with site-specific values
 */
function gcube_customize_manifest(array $manifest): array {
    $site_name = get_bloginfo('name');
    $site_description = get_bloginfo('description');

    // Override with site values
    if (!empty($site_name)) {
        $manifest['name'] = $site_name;
        $manifest['short_name'] = gtemplate_truncate_name($site_name, 12);
    }

    // Operator-set short name wins (empty = derived from the site name)
    $short_name = gcube_mod('pwa_short_name');
    if (!empty($short_name)) {
        $manifest['short_name'] = $short_name;
    }

    if (!empty($site_description)) {
        $manifest['description'] = $site_description;
    }

    // Customizer overrides — canonical pwa_* settings via the schema (the
    // old gcube_pwa_* reads pointed at names nothing registers)
    $theme_color = gcube_mod('pwa_theme_color');
    if (!empty($theme_color)) {
        $manifest['theme_color'] = $theme_color;
    }

    $bg_color = gcube_mod('pwa_background_color');
    if (!empty($bg_color)) {
        $manifest['background_color'] = $bg_color;
    }

    // Set start_url to site root
    $manifest['start_url'] = home_url('/');
    $manifest['scope'] = home_url('/');

    // Customizer icons take precedence over theme/site-icon fallbacks
    $custom_icons = [];
    foreach ([192 => gcube_mod('pwa_icon_192'), 512 => gcube_mod('pwa_icon_512')] as $size => $url) {
        if (!empty($url)) {
            $custom_icons[] = [
                'src' => esc_url($url),
                'sizes' => "{$size}x{$size}",
                'type' => 'image/png',
                'purpose' => 'any maskable',
            ];
        }
    }
    if (!empty($custom_icons)) {
        $manifest['icons'] = $custom_icons;
    }

    // Fix icon paths to be absolute URLs
    if (!empty($manifest['icons'])) {
        foreach ($manifest['icons'] as &$icon) {
            if (isset($icon['src']) && strpos($icon['src'], 'http') !== 0) {
                // Convert relative path to absolute
                $icon['src'] = home_url($icon['src']);
            }
        }
    }

    return $manifest;
}

/**
 * Generate manifest dynamically
 */
function gcube_generate_manifest(): array {
    $site_name = get_bloginfo('name') ?: 'gCube';
    $site_description = get_bloginfo('description') ?: '3D Cube Navigation Experience';

    $manifest = [
        'name' => $site_name,
        'short_name' => gtemplate_truncate_name($site_name, 12),
        'description' => $site_description,
        'start_url' => home_url('/'),
        'display' => 'standalone',
        'background_color' => gcube_mod('pwa_background_color'),
        'theme_color' => gcube_mod('pwa_theme_color'),
        'orientation' => 'any',
        'scope' => home_url('/'),
        'lang' => get_locale(),
        'dir' => (function_exists('is_rtl') && is_rtl()) ? 'rtl' : 'ltr',
        'icons' => [],
    ];

    // Add icons
    $icon_sizes = [72, 96, 128, 144, 152, 192, 384, 512];
    $theme_uri = get_stylesheet_directory_uri();

    foreach ($icon_sizes as $size) {
        $icon_path = "/assets/images/icon-{$size}.png";
        $full_path = get_stylesheet_directory() . $icon_path;

        if (file_exists($full_path)) {
            $manifest['icons'][] = [
                'src' => $theme_uri . $icon_path,
                'sizes' => "{$size}x{$size}",
                'type' => 'image/png',
                'purpose' => ($size >= 192) ? 'any maskable' : 'any'
            ];
        }
    }

    // Fallback to site icon
    if (empty($manifest['icons'])) {
        $site_icon_192 = get_site_icon_url(192);
        $site_icon_512 = get_site_icon_url(512);

        if ($site_icon_192) {
            $manifest['icons'][] = [
                'src' => $site_icon_192,
                'sizes' => '192x192',
                'type' => 'image/png',
                'purpose' => 'any maskable'
            ];
        }
        if ($site_icon_512) {
            $manifest['icons'][] = [
                'src' => $site_icon_512,
                'sizes' => '512x512',
                'type' => 'image/png',
                'purpose' => 'any maskable'
            ];
        }
    }

    return $manifest;
}

/**
 * Generate minimal service worker if file not found
 */
function gcube_generate_minimal_sw(): string {
    $cache_version = 'gcube-v' . wp_get_theme()->get('Version');

    return <<<JS
/**
 * gCube PWA Service Worker (Generated)
 * Version: {$cache_version}
 */

const CACHE_VERSION = '{$cache_version}';
const CACHE_NAME = CACHE_VERSION + '-cache';

// Install: Just activate immediately
self.addEventListener('install', event => {
    console.log('[gCube SW] Installing:', CACHE_VERSION);
    self.skipWaiting();
});

// Activate: Clean old caches
self.addEventListener('activate', event => {
    console.log('[gCube SW] Activating:', CACHE_VERSION);
    event.waitUntil(
        caches.keys().then(names =>
            Promise.all(
                names
                    .filter(n => n.startsWith('gcube-') && n !== CACHE_NAME)
                    .map(n => caches.delete(n))
            )
        ).then(() => self.clients.claim())
    );
});

// Fetch: Network-first with cache fallback
self.addEventListener('fetch', event => {
    // Only handle GET requests
    if (event.request.method !== 'GET') return;

    // Skip wp-admin
    const url = new URL(event.request.url);
    if (url.pathname.startsWith('/wp-admin') || url.pathname.includes('wp-login')) return;

    event.respondWith(
        fetch(event.request)
            .then(response => {
                // Cache successful responses
                if (response && response.ok) {
                    const clone = response.clone();
                    caches.open(CACHE_NAME).then(cache => cache.put(event.request, clone));
                }
                return response;
            })
            .catch(() => caches.match(event.request))
    );
});

console.log('[gCube SW] Service Worker loaded:', CACHE_VERSION);
JS;
}

/**
 * Flush rewrite rules on theme activation
 */
add_action('after_switch_theme', function() {
    // Add our rules
    add_rewrite_rule('^sw\.js$', 'index.php?gcube_pwa_file=sw', 'top');
    add_rewrite_rule('^manifest\.json$', 'index.php?gcube_pwa_file=manifest', 'top');
    add_rewrite_rule('^manifest\.webmanifest$', 'index.php?gcube_pwa_file=manifest', 'top');

    // Flush to apply
    flush_rewrite_rules();

    error_log('[gCube] PWA rewrite rules flushed on theme activation');
});

/**
 * Check and flush rules if needed (once per deployment)
 */
add_action('init', function() {
    $rules = get_option('rewrite_rules', []);

    if (!isset($rules['^sw\.js$'])) {
        flush_rewrite_rules();
        error_log('[gCube] PWA rewrite rules auto-flushed');
    }
}, 99);

/**
 * Output manifest link tag in wp_head
 */
add_action('wp_head', function() {
    if (!gcube_pwa_enabled()) {
        return;
    }
    // Use root URL since we handle rewrites
    echo '<link rel="manifest" href="/manifest.json">' . "\n";
}, 0);

/**
 * Register service worker from wp_footer
 */
add_action('wp_footer', function() {
    // Don't register in admin
    if (is_admin()) {
        return;
    }
    if (!gcube_pwa_enabled()) {
        return;
    }
    $banner = (string) gcube_mod('pwa_install_banner');
    ?>
    <script>
    if ('serviceWorker' in navigator) {
        window.addEventListener('load', function() {
            navigator.serviceWorker.register('/sw.js', { scope: '/' })
                .catch(function(err) {
                    console.error('[gCube] Service Worker registration failed:', err);
                });
        });
    }
    <?php if ($banner !== '') : ?>
    (function() {
        var deferredPrompt = null;
        var DISMISS_KEY = 'gcube-pwa-banner-dismissed';
        window.addEventListener('beforeinstallprompt', function(e) {
            e.preventDefault();
            deferredPrompt = e;
            if (window.localStorage && localStorage.getItem(DISMISS_KEY)) {
                return;
            }
            var bar = document.createElement('div');
            bar.id = 'gcube-pwa-banner';
            bar.setAttribute('role', 'dialog');
            bar.setAttribute('aria-label', 'Install app');
            bar.style.cssText = 'position:fixed;bottom:0;left:0;right:0;z-index:99999;display:flex;align-items:center;gap:12px;padding:10px 14px;background:rgba(17,17,24,.96);color:#d4d4dc;font:14px/1.4 -apple-system,BlinkMacSystemFont,sans-serif;';
            var img = document.createElement('img');
            img.src = <?php echo wp_json_encode(esc_url($banner)); ?>;
            img.alt = '';
            img.style.cssText = 'height:40px;width:auto;border-radius:6px;';
            var btn = document.createElement('button');
            btn.textContent = 'Install';
            btn.style.cssText = 'margin-left:auto;background:#e8c468;border:0;border-radius:6px;color:#111;padding:8px 16px;font:inherit;cursor:pointer;';
            btn.addEventListener('click', function() {
                bar.remove();
                if (deferredPrompt) {
                    deferredPrompt.prompt();
                    deferredPrompt = null;
                }
            });
            var close = document.createElement('button');
            close.textContent = '\u00d7';
            close.setAttribute('aria-label', 'Dismiss');
            close.style.cssText = 'background:none;border:0;color:#8d7944;font-size:20px;cursor:pointer;padding:4px 8px;';
            close.addEventListener('click', function() {
                bar.remove();
                if (window.localStorage) {
                    localStorage.setItem(DISMISS_KEY, '1');
                }
            });
            bar.appendChild(img);
            bar.appendChild(btn);
            bar.appendChild(close);
            document.body.appendChild(bar);
        });
    })();
    <?php endif; ?>
    </script>
    <?php
}, 99);

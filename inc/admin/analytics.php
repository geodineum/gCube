<?php
declare(strict_types=1);
/**
 * gCube Analytics Admin Panel
 *
 * Provides WordPress admin dashboard for viewing visitor analytics,
 * resource consumption, cache efficiency, and user journey stories.
 *
 * @package gCube
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Remember the real hook suffix WordPress assigns the analytics page.
 * The page is a SUBMENU of gcore-dashboard, so the suffix is
 * "{parent}_page_gcube-analytics" — never the toplevel_page_* form.
 * Comparing against the value add_submenu_page() returns is the only
 * spelling that cannot drift.
 */
function gcube_analytics_hook(?string $set = null): string {
    static $hook = '';
    if ($set !== null) {
        $hook = $set;
    }
    return $hook;
}

/**
 * Register the analytics admin menu
 */
add_action('admin_menu', function() {
    $hook = add_submenu_page(
        'gcore-dashboard',
        __('Analytics', 'gcube'),
        __('Analytics', 'gcube'),
        'manage_options',
        'gcube-analytics',
        'gcube_render_analytics_page'
    );
    if (is_string($hook)) {
        gcube_analytics_hook($hook);
    }
});

/**
 * Enqueue admin styles
 */
add_action('admin_enqueue_scripts', function($hook) {
    if ($hook === '' || $hook !== gcube_analytics_hook()) {
        return;
    }

    wp_add_inline_style('wp-admin', gcube_get_analytics_styles());
});

/**
 * Render the analytics admin page
 */
function gcube_render_analytics_page(): void {
    global $gCore;

    // Check permissions
    if (!current_user_can('manage_options')) {
        wp_die(__('You do not have sufficient permissions to access this page.', 'gcube'));
    }

    // Get date range from query params
    $range = sanitize_text_field($_GET['range'] ?? '7d');
    $dates = gcube_get_date_range($range);

    // Try to get AnalyticsManager
    $analytics = null;
    $analyticsData = [];
    $hasAnalytics = false;

    if ($gCore) {
        try {
            $analytics = $gCore->getService('AnalyticsManager');
            if ($analytics && method_exists($analytics, 'isInitialized') && $analytics->isInitialized()) {
                $hasAnalytics = true;
                $analyticsData = gcube_fetch_analytics_data($analytics, $dates);
            }
        } catch (\Throwable $e) {
            error_log('gCube Analytics: Failed to get AnalyticsManager: ' . $e->getMessage());
        }
    }

    ?>
    <div class="wrap gcube-analytics">
        <h1><?php echo esc_html(get_admin_page_title()); ?></h1>

        <!-- Date Range Selector -->
        <div class="gcube-analytics-controls">
            <form method="get" action="">
                <input type="hidden" name="page" value="gcube-analytics">
                <select name="range" onchange="this.form.submit()">
                    <option value="1d" <?php selected($range, '1d'); ?>>Today</option>
                    <option value="7d" <?php selected($range, '7d'); ?>>Last 7 Days</option>
                    <option value="30d" <?php selected($range, '30d'); ?>>Last 30 Days</option>
                    <option value="90d" <?php selected($range, '90d'); ?>>Last 90 Days</option>
                </select>
            </form>
        </div>

        <?php if (!$hasAnalytics): ?>
            <div class="notice notice-warning">
                <p><?php _e('AnalyticsManager is not available. Ensure gCore is properly initialized.', 'gcube'); ?></p>
            </div>
        <?php else: ?>

            <!-- Summary Cards -->
            <div class="gcube-analytics-cards">
                <div class="gcube-card">
                    <h3><?php _e('Unique Visitors', 'gcube'); ?></h3>
                    <div class="gcube-card-value"><?php echo number_format($analyticsData['total_visitors'] ?? 0); ?></div>
                    <div class="gcube-card-trend <?php echo ($analyticsData['visitor_trend'] ?? 0) >= 0 ? 'positive' : 'negative'; ?>">
                        <?php echo ($analyticsData['visitor_trend'] ?? 0) >= 0 ? '+' : ''; ?><?php echo number_format($analyticsData['visitor_trend'] ?? 0, 1); ?>%
                    </div>
                </div>

                <div class="gcube-card">
                    <h3><?php _e('Page Views', 'gcube'); ?></h3>
                    <div class="gcube-card-value"><?php echo number_format($analyticsData['total_pageviews'] ?? 0); ?></div>
                    <div class="gcube-card-subtitle">
                        <?php echo number_format($analyticsData['avg_pages_per_visitor'] ?? 0, 1); ?> <?php _e('per visitor', 'gcube'); ?>
                    </div>
                </div>

                <div class="gcube-card">
                    <h3><?php _e('Cache Efficiency', 'gcube'); ?></h3>
                    <div class="gcube-card-value"><?php echo number_format($analyticsData['cache_hit_rate'] ?? 0, 1); ?>%</div>
                    <div class="gcube-card-subtitle">
                        <?php echo gcube_format_bytes($analyticsData['bandwidth_saved'] ?? 0); ?> <?php _e('saved', 'gcube'); ?>
                    </div>
                </div>

                <div class="gcube-card">
                    <h3><?php _e('Avg. Cost per Visitor', 'gcube'); ?></h3>
                    <div class="gcube-card-value"><?php echo number_format($analyticsData['avg_requests_per_visitor'] ?? 0, 1); ?></div>
                    <div class="gcube-card-subtitle">
                        <?php _e('requests', 'gcube'); ?> / <?php echo gcube_format_bytes($analyticsData['avg_bytes_per_visitor'] ?? 0); ?>
                    </div>
                </div>
            </div>

            <!-- User Stories Section -->
            <div class="gcube-analytics-section">
                <h2><?php _e('User Stories (Face Navigation Patterns)', 'gcube'); ?></h2>
                <p class="description"><?php _e('How visitors navigate through the cube faces. Privacy-safe: based on hashed identifiers.', 'gcube'); ?></p>

                <?php if (!empty($analyticsData['user_stories'])): ?>
                    <table class="widefat gcube-stories-table">
                        <thead>
                            <tr>
                                <th><?php _e('Journey Pattern', 'gcube'); ?></th>
                                <th><?php _e('Visitors', 'gcube'); ?></th>
                                <th><?php _e('% of Total', 'gcube'); ?></th>
                                <th><?php _e('Avg. Time', 'gcube'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($analyticsData['user_stories'] as $story): ?>
                                <tr>
                                    <td>
                                        <span class="gcube-journey-path">
                                            <?php echo esc_html(gcube_format_journey($story['path'])); ?>
                                        </span>
                                    </td>
                                    <td><?php echo number_format($story['count']); ?></td>
                                    <td><?php echo number_format($story['percentage'], 1); ?>%</td>
                                    <td><?php echo esc_html($story['avg_duration']); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <p class="gcube-no-data"><?php _e('No user journey data available yet.', 'gcube'); ?></p>
                <?php endif; ?>
            </div>

            <!-- Top Faces Section -->
            <div class="gcube-analytics-section">
                <h2><?php _e('Most Viewed Faces', 'gcube'); ?></h2>

                <?php if (!empty($analyticsData['top_pages'])): ?>
                    <table class="widefat">
                        <thead>
                            <tr>
                                <th><?php _e('Face / Page', 'gcube'); ?></th>
                                <th><?php _e('Views', 'gcube'); ?></th>
                                <th><?php _e('Unique Visitors', 'gcube'); ?></th>
                                <th><?php _e('Entry Rate', 'gcube'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($analyticsData['top_pages'] as $page): ?>
                                <tr>
                                    <td>
                                        <strong><?php echo esc_html($page['label']); ?></strong>
                                        <br><small><?php echo esc_html($page['uri']); ?></small>
                                    </td>
                                    <td><?php echo number_format($page['views']); ?></td>
                                    <td><?php echo number_format($page['unique_visitors']); ?></td>
                                    <td><?php echo number_format($page['entry_rate'], 1); ?>%</td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <p class="gcube-no-data"><?php _e('No page view data available yet.', 'gcube'); ?></p>
                <?php endif; ?>
            </div>

            <!-- Resource Consumption Section -->
            <div class="gcube-analytics-section">
                <h2><?php _e('Resource Consumption', 'gcube'); ?></h2>
                <p class="description"><?php _e('Server requests and bandwidth per visitor. Lower is better (indicates effective caching).', 'gcube'); ?></p>

                <div class="gcube-resource-grid">
                    <div class="gcube-resource-item">
                        <h4><?php _e('Total Requests', 'gcube'); ?></h4>
                        <div class="gcube-resource-value"><?php echo number_format($analyticsData['total_requests'] ?? 0); ?></div>
                    </div>
                    <div class="gcube-resource-item">
                        <h4><?php _e('Total Bandwidth', 'gcube'); ?></h4>
                        <div class="gcube-resource-value"><?php echo gcube_format_bytes($analyticsData['total_bytes'] ?? 0); ?></div>
                    </div>
                    <div class="gcube-resource-item">
                        <h4><?php _e('Cache Hits', 'gcube'); ?></h4>
                        <div class="gcube-resource-value"><?php echo number_format($analyticsData['cache_hits'] ?? 0); ?></div>
                    </div>
                    <div class="gcube-resource-item">
                        <h4><?php _e('Cache Misses', 'gcube'); ?></h4>
                        <div class="gcube-resource-value"><?php echo number_format($analyticsData['cache_misses'] ?? 0); ?></div>
                    </div>
                </div>

                <?php if (!empty($analyticsData['hourly_requests'])): ?>
                    <h4><?php _e('Requests Over Time', 'gcube'); ?></h4>
                    <div class="gcube-chart-container">
                        <canvas id="gcube-requests-chart"></canvas>
                    </div>
                    <script>
                        document.addEventListener('DOMContentLoaded', function() {
                            if (typeof Chart !== 'undefined') {
                                var ctx = document.getElementById('gcube-requests-chart').getContext('2d');
                                new Chart(ctx, {
                                    type: 'line',
                                    data: {
                                        labels: <?php echo json_encode(array_keys($analyticsData['hourly_requests'])); ?>,
                                        datasets: [{
                                            label: '<?php _e('Requests', 'gcube'); ?>',
                                            data: <?php echo json_encode(array_values($analyticsData['hourly_requests'])); ?>,
                                            borderColor: '#e51022',
                                            backgroundColor: 'rgba(229, 16, 34, 0.1)',
                                            fill: true,
                                            tension: 0.4
                                        }]
                                    },
                                    options: {
                                        responsive: true,
                                        maintainAspectRatio: false,
                                        scales: {
                                            y: { beginAtZero: true }
                                        }
                                    }
                                });
                            }
                        });
                    </script>
                <?php endif; ?>
            </div>

            <!-- Privacy Note -->
            <div class="gcube-analytics-section gcube-privacy-note">
                <h3><?php _e('Privacy-First Analytics', 'gcube'); ?></h3>
                <ul>
                    <li><?php _e('No raw IP addresses stored - only hashed identifiers', 'gcube'); ?></li>
                    <li><?php _e('Data automatically expires after 90 days', 'gcube'); ?></li>
                    <li><?php _e('No third-party tracking or cookies', 'gcube'); ?></li>
                    <li><?php _e('GDPR-friendly by design', 'gcube'); ?></li>
                </ul>
            </div>

        <?php endif; ?>
    </div>
    <?php
}

/**
 * Fetch analytics data from AnalyticsManager
 *
 * @param object $analytics AnalyticsManager instance
 * @param array $dates Date range array
 * @return array Analytics data
 */
function gcube_fetch_analytics_data($analytics, array $dates): array {
    $data = [
        'total_visitors' => 0,
        'total_pageviews' => 0,
        'visitor_trend' => 0,
        'avg_pages_per_visitor' => 0,
        'cache_hit_rate' => 0,
        'bandwidth_saved' => 0,
        'avg_requests_per_visitor' => 0,
        'avg_bytes_per_visitor' => 0,
        'total_requests' => 0,
        'total_bytes' => 0,
        'cache_hits' => 0,
        'cache_misses' => 0,
        'top_pages' => [],
        'user_stories' => [],
        'hourly_requests' => [],
    ];

    try {
        // Aggregate data across date range
        foreach ($dates as $date) {
            // Get daily stats if method exists
            if (method_exists($analytics, 'getDailyStats')) {
                $stats = $analytics->getDailyStats($date);
                $data['total_visitors'] += $stats['unique_visitors'] ?? 0;
                $data['total_pageviews'] += $stats['pageviews'] ?? 0;
            }

            // Get top pages
            if (method_exists($analytics, 'getTopPages')) {
                $pages = $analytics->getTopPages($date, 10);
                foreach ($pages as $uri => $views) {
                    if (!isset($data['top_pages'][$uri])) {
                        $data['top_pages'][$uri] = [
                            'uri' => $uri,
                            'label' => gcube_uri_to_face_label($uri),
                            'views' => 0,
                            'unique_visitors' => 0,
                            'entry_rate' => 0,
                        ];
                    }
                    $data['top_pages'][$uri]['views'] += $views;
                }
            }

            // Get cache efficiency
            if (method_exists($analytics, 'getCacheEfficiency')) {
                $cache = $analytics->getCacheEfficiency($date);
                $data['cache_hits'] += $cache['cache_hits'] ?? 0;
                $data['cache_misses'] += $cache['cache_misses'] ?? 0;
                $data['bandwidth_saved'] += $cache['bytes_saved'] ?? 0;
            }

            // Get resource costs
            if (method_exists($analytics, 'getVisitorResourceCosts')) {
                $costs = $analytics->getVisitorResourceCosts($date);
                $data['total_requests'] += $costs['total_requests'] ?? 0;
                $data['total_bytes'] += $costs['total_bytes'] ?? 0;
            }
        }

        // Calculate derived metrics
        if ($data['total_visitors'] > 0) {
            $data['avg_pages_per_visitor'] = $data['total_pageviews'] / $data['total_visitors'];
            $data['avg_requests_per_visitor'] = $data['total_requests'] / $data['total_visitors'];
            $data['avg_bytes_per_visitor'] = $data['total_bytes'] / $data['total_visitors'];
        }

        $totalCacheOps = $data['cache_hits'] + $data['cache_misses'];
        if ($totalCacheOps > 0) {
            $data['cache_hit_rate'] = ($data['cache_hits'] / $totalCacheOps) * 100;
        }

        // Convert top_pages to sorted array
        $data['top_pages'] = array_values($data['top_pages']);
        usort($data['top_pages'], function($a, $b) {
            return $b['views'] - $a['views'];
        });
        $data['top_pages'] = array_slice($data['top_pages'], 0, 10);

        // Get user stories (visitor journeys)
        if (method_exists($analytics, 'getVisitorJourneys')) {
            $data['user_stories'] = $analytics->getVisitorJourneys($dates[0], 20);
        }

        // Get metric history for chart
        if (method_exists($analytics, 'getMetricHistory')) {
            $history = $analytics->getMetricHistory('requests', count($dates) * 24);
            if (!empty($history)) {
                $data['hourly_requests'] = $history;
            }
        }

    } catch (\Throwable $e) {
        error_log('gCube Analytics: Error fetching data: ' . $e->getMessage());
    }

    return $data;
}

/**
 * Get date range array based on range selector
 *
 * @param string $range Range string (1d, 7d, 30d, 90d)
 * @return array Array of date strings (YYYYMMDD format)
 */
function gcube_get_date_range(string $range): array {
    $rangeMap = [
        '1d' => 1,
        '7d' => 7,
        '30d' => 30,
        '90d' => 90,
    ];
    $days = $rangeMap[$range] ?? 7;

    $dates = [];
    for ($i = 0; $i < $days; $i++) {
        $dates[] = date('Ymd', strtotime("-{$i} days"));
    }

    return $dates;
}

/**
 * Convert URI to cube face label
 *
 * @param string $uri Request URI
 * @return string Human-readable label
 */
function gcube_uri_to_face_label(string $uri): string {
    // Check for face parameter
    if (preg_match('/[?&]face=(\d+)/', $uri, $matches)) {
        $faceNum = (int)$matches[1];
        $faceNames = [
            0 => 'Top Face',
            1 => 'Front Face (Home)',
            2 => 'Right Face',
            3 => 'Back Face',
            4 => 'Left Face',
            5 => 'Bottom Face',
        ];
        return $faceNames[$faceNum] ?? "Face {$faceNum}";
    }

    // Homepage
    if ($uri === '/' || $uri === '') {
        return 'Home (Front Face)';
    }

    // Clean up URI for display
    $clean = trim($uri, '/');
    return ucwords(str_replace(['-', '_', '/'], [' ', ' ', ' > '], $clean));
}

/**
 * Format journey path for display
 *
 * @param array|string $path Journey path
 * @return string Formatted path
 */
function gcube_format_journey($path): string {
    if (is_string($path)) {
        $path = explode(' > ', $path);
    }

    $faces = array_map(function($step) {
        return gcube_uri_to_face_label($step);
    }, $path);

    // Use arrow between steps
    return implode(' → ', array_slice($faces, 0, 5)) . (count($faces) > 5 ? ' →...' : '');
}

/**
 * Format bytes to human readable
 *
 * @param int $bytes Bytes
 * @return string Formatted string
 */
function gcube_format_bytes(int $bytes): string {
    if ($bytes === 0) return '0 B';

    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    $i = floor(log($bytes, 1024));
    $i = min($i, count($units) - 1);

    return round($bytes / pow(1024, $i), 2) . ' ' . $units[$i];
}

/**
 * Get admin panel CSS styles
 *
 * @return string CSS
 */
function gcube_get_analytics_styles(): string {
    return '
        .gcube-analytics { max-width: 1400px; }

        .gcube-analytics-controls {
            margin: 20px 0;
            padding: 15px;
            background: #fff;
            border: 1px solid #ccd0d4;
            border-radius: 4px;
        }

        .gcube-analytics-cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin: 20px 0;
        }

        .gcube-card {
            background: #fff;
            border: 1px solid #ccd0d4;
            border-radius: 8px;
            padding: 20px;
            text-align: center;
        }

        .gcube-card h3 {
            margin: 0 0 10px;
            font-size: 14px;
            color: #646970;
            font-weight: 500;
        }

        .gcube-card-value {
            font-size: 36px;
            font-weight: 600;
            color: #1d2327;
            line-height: 1.2;
        }

        .gcube-card-trend {
            font-size: 14px;
            margin-top: 8px;
        }

        .gcube-card-trend.positive { color: #00a32a; }
        .gcube-card-trend.negative { color: #d63638; }

        .gcube-card-subtitle {
            font-size: 13px;
            color: #646970;
            margin-top: 8px;
        }

        .gcube-analytics-section {
            background: #fff;
            border: 1px solid #ccd0d4;
            border-radius: 8px;
            padding: 20px;
            margin: 20px 0;
        }

        .gcube-analytics-section h2 {
            margin: 0 0 10px;
            font-size: 18px;
            border-bottom: 1px solid #ccd0d4;
            padding-bottom: 10px;
        }

        .gcube-journey-path {
            font-family: monospace;
            font-size: 13px;
            background: #f6f7f7;
            padding: 4px 8px;
            border-radius: 4px;
            display: inline-block;
        }

        .gcube-stories-table td { vertical-align: middle; }

        .gcube-resource-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 15px;
            margin: 15px 0;
        }

        .gcube-resource-item {
            background: #f6f7f7;
            padding: 15px;
            border-radius: 6px;
            text-align: center;
        }

        .gcube-resource-item h4 {
            margin: 0 0 8px;
            font-size: 12px;
            color: #646970;
            text-transform: uppercase;
        }

        .gcube-resource-value {
            font-size: 24px;
            font-weight: 600;
            color: #1d2327;
        }

        .gcube-chart-container {
            height: 250px;
            margin: 20px 0;
        }

        .gcube-privacy-note {
            background: #f0f6fc;
            border-color: #72aee6;
        }

        .gcube-privacy-note h3 {
            color: #2271b1;
            margin: 0 0 10px;
        }

        .gcube-privacy-note ul {
            margin: 0;
            padding-left: 20px;
        }

        .gcube-privacy-note li {
            margin: 5px 0;
            color: #2c3338;
        }

        .gcube-no-data {
            text-align: center;
            color: #646970;
            padding: 40px;
            font-style: italic;
        }
    ';
}

/**
 * Enqueue Chart.js for admin.
 *
 * Self-host chart.js instead of pulling from
 * cdn.jsdelivr.net. Pre-fix, a CDN compromise (or any byte change in
 * an upstream) would have yielded persistent admin XSS with
 * manage_options — chart.js runs on the gCube analytics admin page
 * with full DOM + nonce access. Post-fix, the file ships from
 * `assets/js/chart.umd.min.js` (4.4.1, ~205 KB) under the plugin
 * itself, removing the CDN dependency entirely. Same approach as
 * htmx.min.js (already self-hosted at the same path).
 *
 * To upgrade Chart.js: replace assets/js/chart.umd.min.js with the
 * desired release from https://github.com/chartjs/Chart.js/releases
 * and bump the version string below.
 */
add_action('admin_enqueue_scripts', function($hook) {
    if ($hook === '' || $hook !== gcube_analytics_hook()) {
        return;
    }

    wp_enqueue_script(
        'chartjs',
        get_stylesheet_directory_uri() . '/assets/js/chart.umd.min.js',
        [],
        '4.4.1',
        true
    );
});

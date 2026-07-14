<?php
declare(strict_types=1);
/**
 * WP-CLI Utility Commands
 *
 * Miscellaneous utility commands.
 *
 * @package    gCube
 * @subpackage CLI\Commands
 * @since      2.0.0
 */

if (!defined('WP_CLI') || !WP_CLI) {
    return;
}

/**
 * Utility commands
 */
class gCube_CLI_Utilities
{
    /**
     * Show or generate viewkey for environment gate
     *
     * ## OPTIONS
     *
     * [--regenerate]
     * : Generate a new viewkey (overwrites existing)
     *
     * [--copy]
     * : Output just the viewkey (for scripting/copying)
     *
     * ## EXAMPLES
     *
     *     wp gcube viewkey
     *     wp gcube viewkey --copy
     *     wp gcube viewkey --regenerate
     *
     * @when after_wp_load
     */
    public function viewkey($args, $assoc_args)
    {
        $config = \gTemplate\load_registration_config();
        if (!$config) {
            WP_CLI::error('Failed to load site configuration');
            return;
        }

        $environment = $config['metadata']['environment'] ?? 'production';
        $viewkey = $config['security']['viewkey'] ?? '';
        $site_id = $config['site_id'] ?? 'unknown';
        $copyOnly = isset($assoc_args['copy']);

        if (isset($assoc_args['regenerate'])) {
            $viewkey = bin2hex(random_bytes(16));

            $config_file = ABSPATH . 'wp-config-geodineum.yaml';
            if (!file_exists($config_file)) {
                $config_file = get_stylesheet_directory() . '/registration.local.yaml';
            }

            if (file_exists($config_file) && is_writable($config_file)) {
                $content = file_get_contents($config_file);

                if (preg_match('/^(\s*viewkey:\s*)["\']?[^"\'\n]*["\']?\s*$/m', $content)) {
                    $content = preg_replace(
                        '/^(\s*viewkey:\s*)["\']?[^"\'\n]*["\']?\s*$/m',
                        '$1"' . $viewkey . '"',
                        $content
                    );
                } else {
                    if (preg_match('/^security:\s*$/m', $content)) {
                        $content = preg_replace(
                            '/^(security:\s*)$/m',
                            "$1\n  viewkey: \"$viewkey\"",
                            $content
                        );
                    }
                }

                file_put_contents($config_file, $content);
                WP_CLI::success('Viewkey regenerated!');
            } else {
                WP_CLI::warning("Cannot write to config file. Add this manually:");
                WP_CLI::line("  viewkey: \"$viewkey\"");
            }
        }

        if ($copyOnly) {
            if (empty($viewkey)) {
                WP_CLI::error('No viewkey configured');
            } else {
                WP_CLI::line($viewkey);
            }
            return;
        }

        WP_CLI::line('');
        WP_CLI::line('=== gCube Environment Gate ===');
        WP_CLI::line('');
        WP_CLI::line('Site ID:     ' . $site_id);
        WP_CLI::line('Environment: ' . $environment);
        WP_CLI::line('');

        if ($environment === 'production') {
            WP_CLI::line('Status: Environment gate is INACTIVE (production)');
            WP_CLI::line('');
            WP_CLI::line('The environment gate only activates for non-production environments.');
            WP_CLI::line('Change metadata.environment in your config to enable it.');
        } else {
            WP_CLI::line('Status: Environment gate is ACTIVE');
            WP_CLI::line('');

            if (empty($viewkey)) {
                WP_CLI::warning('No viewkey configured!');
                WP_CLI::line('');
                WP_CLI::line('Anonymous visitors will see the gate screen but cannot enter a viewkey.');
                WP_CLI::line('Generate one with: wp gcube viewkey --regenerate');
            } else {
                WP_CLI::line('Viewkey: ' . $viewkey);
                WP_CLI::line('');
                WP_CLI::line('Share this viewkey with clients to preview the site without WordPress login.');
                WP_CLI::line('');
                WP_CLI::line('Preview URL: ' . home_url('/?viewkey=' . $viewkey));
            }
        }

        WP_CLI::line('');
    }
}

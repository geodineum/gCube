<?php
declare(strict_types=1);
/**
 * WP-CLI Commands for gCube
 *
 * Cube-specific WP-CLI commands. Ecosystem-wide commands (registration,
 * config get/set, AIO, etc.) live in the parent theme and are exposed via
 * `wp gtemplate ...`. The `wp gcube` namespace is reserved for behaviour
 * that is genuinely cube-theme-specific.
 *
 * @package    gCube
 * @subpackage CLI
 * @since      2.0.0
 *
 * AVAILABLE COMMANDS:
 * ===================
 *   wp gcube viewkey [--regenerate]   Manage environment viewkey
 *
 * MOVED TO `wp gtemplate ...`:
 *   register / status / deregister / refresh / capability  (parent: registration)
 *   config / sync-config / runtime-{get,set,list}          (parent: config)
 *   aio-generate / aio-regenerate-all / aio-status / etc.  (parent: aio)
 *
 * If you previously typed `wp gcube config get <cat> <key>`, run
 * `wp gtemplate config get <cat> <key>` instead. Same logic, lives in the
 * parent theme to avoid duplicate maintenance surface (Ch.1.A pre-launch
 * cleanup, ROADMAP.md §A.1).
 */

if (!defined('WP_CLI') || !WP_CLI) {
    return;
}

// Cube-specific command classes
require_once __DIR__ . '/commands/utilities.php';

/**
 * gCube CLI — cube-specific command router.
 *
 * The class is registered as the handler for `wp gcube <subcommand>`. Each
 * public method is a subcommand; constructor wires the underlying handler
 * classes lazily so a missing dependency in one handler can't break the
 * others.
 */
class gCube_CLI
{
    /** @var gCube_CLI_Utilities|null */
    private $utilities = null;

    private function utilities(): gCube_CLI_Utilities
    {
        if ($this->utilities === null) {
            $this->utilities = new gCube_CLI_Utilities();
        }
        return $this->utilities;
    }

    // =========================================================================
    // Utility Commands (cube-specific)
    // =========================================================================

    /**
     * Manage the environment viewkey for non-production gates.
     *
     * ## OPTIONS
     *
     * [--regenerate]
     * : Generate a new viewkey and update the runtime config.
     *
     * @when after_wp_load
     */
    public function viewkey($args, $assoc_args)
    {
        $this->utilities()->viewkey($args, $assoc_args);
    }
}

// Register CLI command
WP_CLI::add_command('gcube', 'gCube_CLI');

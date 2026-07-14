<?php
declare(strict_types=1);
/**
 * gNodeConfigLoader — namespace alias for parent theme's implementation
 *
 * The constellation-aware config loader lives in gTemplate (parent theme).
 * This file provides a namespace alias so existing gCube code continues
 * to work with \gCube\gNodeConfigLoader::get() calls.
 *
 * @package gCube
 * @since 2.0.0
 */

namespace gCube;

// The parent theme's autoload.php loads the real class in \gTemplate namespace.
// This alias lets child theme code reference it without namespace changes.
if (!class_exists(__NAMESPACE__ . '\\gNodeConfigLoader')) {
    class_alias('\\gTemplate\\gNodeConfigLoader', __NAMESPACE__ . '\\gNodeConfigLoader');
}

#!/bin/bash
# Fix early page cache for existing sites
# Adds gcore-mu symlink and loader for ~80ms speedup

set -e

SITE_DIR="${1:-/var/www/example.com}"
GCORE_PATH="/opt/geodineum/gCore"
MU_PLUGINS="${SITE_DIR}/wp-content/mu-plugins"

echo "=== Adding Early Page Cache to ${SITE_DIR} ==="

# Check if gcore-mu exists in gCore
if [[ ! -d "${GCORE_PATH}/gcore-mu" ]]; then
    echo "ERROR: ${GCORE_PATH}/gcore-mu not found"
    exit 1
fi

# Create gcore-mu symlink
if [[ ! -L "${MU_PLUGINS}/gcore-mu" ]]; then
    sudo ln -sf "${GCORE_PATH}/gcore-mu" "${MU_PLUGINS}/gcore-mu"
    sudo chown -h www-data:www-data "${MU_PLUGINS}/gcore-mu"
    echo "✓ gcore-mu symlinked"
else
    echo "✓ gcore-mu symlink already exists"
fi

# Create gcore-mu.php loader
if [[ ! -f "${MU_PLUGINS}/gcore-mu.php" ]]; then
    sudo tee "${MU_PLUGINS}/gcore-mu.php" > /dev/null << 'EOF'
<?php
/**
 * gCore MU-Plugin Loader
 * Loads gCore from /opt/geodineum/gCore/gcore-mu/
 * Includes early-page-cache.php for ~80ms speedup
 */
require_once __DIR__ . '/gcore-mu/gcore-loader.php';
EOF
    sudo chown www-data:www-data "${MU_PLUGINS}/gcore-mu.php"
    echo "✓ gcore-mu.php loader created"
else
    echo "✓ gcore-mu.php loader already exists"
fi

echo ""
echo "=== Testing TTFB ==="
for i in 1 2 3; do
    curl -s -w "TTFB: %{time_starttransfer}s\n" -o /dev/null "https://$(basename ${SITE_DIR})/"
done

echo ""
echo "=== Done! Early page cache enabled ==="

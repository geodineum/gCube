#!/bin/bash
# 403 Forbidden Diagnostic Script
# Run with: sudo bash ~/gh/gCube/scripts/diagnose-403.sh

echo "=============================================="
echo "403 FORBIDDEN ROOT CAUSE ANALYSIS"
echo "Generated: $(date)"
echo "=============================================="

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

echo ""
echo "=== 1. FAIL2BAN STATUS ==="
echo "--- Active Jails ---"
fail2ban-client status 2>/dev/null || echo "fail2ban not running"
echo ""
echo "--- f2b-WordPress Chain (checking IP: ${CHECK_IP:?usage: CHECK_IP=<your-ip> $0}) ---"
iptables -L f2b-WordPress -n 2>/dev/null | grep -E "${CHECK_IP}|target" || echo "Chain doesn't exist"
echo ""
echo "--- f2b-apache-wp-scan Chain ---"
iptables -L f2b-apache-wp-scan -n 2>/dev/null | grep -E "${CHECK_IP}|target" || echo "Chain doesn't exist"

echo ""
echo "=== 2. APACHE ERROR LOGS (Last 50 lines with 403/forbidden/denied) ==="
echo "--- site1.example.com ---"
tail -200 /var/log/apache2/site1.example.com_error.log 2>/dev/null | grep -iE "403|forbidden|denied|permission|fatal|failed" | tail -20
echo ""
echo "--- site2.example.com ---"
tail -200 /var/log/apache2/site2.example.com_error.log 2>/dev/null | grep -iE "403|forbidden|denied|permission|fatal|failed" | tail -20
echo ""
echo "--- Main error.log ---"
tail -200 /var/log/apache2/error.log 2>/dev/null | grep -iE "403|forbidden|denied|permission|fatal|failed" | tail -20

echo ""
echo "=== 3. APACHE ACCESS LOGS (Recent requests from your IP) ==="
echo "--- All sites ---"
grep "${CHECK_IP}" /var/log/apache2/*access*.log 2>/dev/null | tail -30

echo ""
echo "=== 4. PHP-FPM ERROR LOGS ==="
tail -50 /var/log/php*-fpm.log 2>/dev/null | grep -iE "fatal|error|failed" | tail -20
tail -50 /var/log/php*/php*-fpm.log 2>/dev/null | grep -iE "fatal|error|failed" | tail -20

echo ""
echo "=== 5. HTACCESS FILES ==="
echo "--- Global /var/www/.htaccess (wp-login rule) ---"
grep -A2 -B2 "wp-login" /var/www/.htaccess 2>/dev/null

echo ""
echo "--- site1.example.com root .htaccess ---"
cat /var/www/site1.example.com/.htaccess 2>/dev/null || echo "No .htaccess in site root"

echo ""
echo "--- site2.example.com root .htaccess ---"
cat /var/www/site2.example.com/.htaccess 2>/dev/null || echo "No .htaccess in site root"

echo ""
echo "=== 6. FILE PERMISSIONS CHECK ==="
echo "--- /var/www/site1.example.com ---"
ls -la /var/www/site1.example.com/ 2>/dev/null | head -15
echo ""
echo "--- wp-admin directory ---"
ls -la /var/www/site1.example.com/wp-admin/ 2>/dev/null | head -5
echo ""
echo "--- wp-content/mu-plugins (gCore) ---"
ls -la /var/www/site1.example.com/wp-content/mu-plugins/ 2>/dev/null

echo ""
echo "=== 7. GCORE SYMLINK CHECK ==="
echo "--- mu-plugins gcore symlink ---"
ls -la /var/www/site1.example.com/wp-content/mu-plugins/gcore 2>/dev/null || echo "No gcore symlink"
ls -la /var/www/site2.example.com/wp-content/mu-plugins/gcore 2>/dev/null || echo "No gcore symlink"

echo ""
echo "=== 8. PHP AUTOLOAD / TRAIT FILES CHECK ==="
echo "--- TemplateLibrary traits (previously caused 403) ---"
ls -la /opt/geodineum/gCore/Modules/Managers/Base/TemplateLibrary/Traits/ 2>/dev/null || echo "Directory missing!"

echo ""
echo "--- All trait directories in gCore ---"
find /opt/geodineum/gCore/Modules/Managers/Base/*/Traits -name "*.php" 2>/dev/null | head -20

echo ""
echo "=== 9. CURL TESTS FROM SERVER ==="
echo "--- site1.example.com homepage ---"
curl -s -o /dev/null -w "HTTP: %{http_code}, Size: %{size_download} bytes\n" https://site1.example.com/

echo "--- site1.example.com wp-login (no key) ---"
curl -s -o /dev/null -w "HTTP: %{http_code} (should be 403)\n" https://site1.example.com/wp-login.php

echo "--- site1.example.com wp-login (with key) ---"
curl -s -o /dev/null -w "HTTP: %{http_code} (should be 200)\n" "https://site1.example.com/wp-login.php?k=0ab3ffc7fa9e9309c2510b451049826a"

echo "--- site1.example.com wp-admin (no login) ---"
curl -s -o /dev/null -w "HTTP: %{http_code}, Redirect: %{redirect_url}\n" -L --max-redirs 0 https://site1.example.com/wp-admin/

echo ""
echo "=== 10. WORDPRESS DEBUG LOG ==="
tail -50 /var/www/site1.example.com/wp-content/debug.log 2>/dev/null || echo "No debug.log (WP_DEBUG_LOG not enabled)"

echo ""
echo "=== 11. RECENT SYSTEMD JOURNAL (Apache/PHP errors) ==="
journalctl -u apache2 --since "10 minutes ago" --no-pager 2>/dev/null | tail -20
journalctl -u "php*-fpm" --since "10 minutes ago" --no-pager 2>/dev/null | tail -20

echo ""
echo "=============================================="
echo "ANALYSIS COMPLETE"
echo "=============================================="
echo ""
echo "KEY THINGS TO CHECK:"
echo "1. Is your IP (${CHECK_IP}) still in any fail2ban chain?"
echo "2. Are there PHP fatal errors in the logs?"
echo "3. Does wp-admin redirect to wp-login.php WITHOUT the ?k= key?"
echo "4. Are all gCore trait files present?"
echo ""

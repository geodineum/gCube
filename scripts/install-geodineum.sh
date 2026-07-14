#!/bin/bash
#
# DEPRECATED — Use the unified Geodineum CLI instead
# ====================================================
#
# This script has been superseded by the `geodineum` CLI which consolidates
# all site deployment into a single entry point.
#
# New usage:
#   sudo geodineum new site <domain> --theme gcube [--env production]
#
# The canonical WordPress installer lives in gTemplate-wp and handles
# all gCore-based child themes via the --theme flag.
#
# Direct gTemplate-wp usage (if you don't have the geodineum CLI):
#   sudo /opt/geodineum/gTemplate-wp/scripts/install-geodineum.sh <domain> \
#     --theme gcube --theme-path /opt/geodineum/gCube [environment]
#
# For gNode onboarding (ACL, streams, discovery):
#   /opt/geodineum/gNode/scripts/onboard-service.sh <site_id> [--yaml /path]
#

set -euo pipefail

RED='\033[0;31m'
YELLOW='\033[1;33m'
CYAN='\033[0;36m'
BOLD='\033[1m'
NC='\033[0m'

echo ""
echo -e "${YELLOW}╔═══════════════════════════════════════════════════════════════╗${NC}"
echo -e "${YELLOW}║${NC}  ${BOLD}DEPRECATED${NC} — This installer has moved to the Geodineum CLI   ${YELLOW}║${NC}"
echo -e "${YELLOW}╚═══════════════════════════════════════════════════════════════╝${NC}"
echo ""
echo -e "  Use the unified CLI instead:"
echo ""
echo -e "    ${CYAN}sudo geodineum new site <domain> --theme gcube${NC}"
echo ""
echo -e "  Or use gTemplate-wp directly:"
echo ""
echo -e "    ${CYAN}sudo /opt/geodineum/gTemplate-wp/scripts/install-geodineum.sh <domain> \\${NC}"
echo -e "    ${CYAN}  --theme gcube --theme-path /opt/geodineum/gCube [environment]${NC}"
echo ""

# Attempt to forward if geodineum CLI is available
if command -v geodineum &>/dev/null && [[ $# -ge 1 ]]; then
    domain="$1"
    environment="${2:-staging}"
    echo -e "  ${BOLD}Forwarding to geodineum CLI...${NC}"
    echo ""
    exec geodineum new site "$domain" --theme gcube --env "$environment"
fi

# Attempt to forward to gTemplate-wp directly
GEODINEUM_ROOT="${GEODINEUM_ROOT:-/opt/geodineum}"
INSTALL_SCRIPT="${GEODINEUM_ROOT}/gTemplate-wp/scripts/install-geodineum.sh"

if [[ -x "$INSTALL_SCRIPT" ]] && [[ $# -ge 1 ]]; then
    domain="$1"
    environment="${2:-staging}"
    echo -e "  ${BOLD}Forwarding to gTemplate-wp installer...${NC}"
    echo ""
    exec "$INSTALL_SCRIPT" "$domain" --theme gcube --theme-path "${GEODINEUM_ROOT}/gCube" "$environment"
fi

echo -e "  Install the CLI: ${CYAN}sudo ln -sf /opt/geodineum/Geodineum/geodineum /usr/local/bin/geodineum${NC}"
echo ""
exit 1

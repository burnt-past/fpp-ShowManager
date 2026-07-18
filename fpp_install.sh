#!/bin/bash
# FPP runs <plugin>/fpp_install.sh after installing OR updating the plugin.
# Stop any daemons from the OLD code and start the freshly-pulled version, so
# an update never leaves a stale copy running alongside the new one.
PLUGIN_DIR="$(cd "$(dirname "$0")" && pwd)"
exec "$PLUGIN_DIR/scripts/restart_daemons.sh"

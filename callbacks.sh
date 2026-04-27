#!/bin/bash
PLUGIN_DIR="$(dirname "$0")"
BRIDGE="$PLUGIN_DIR/Scripts/xr18_bridge.py"
SCHEDULER="$PLUGIN_DIR/Scripts/show_scheduler.py"

case "$1" in
    postBoot)
        pkill -f xr18_bridge.py    2>/dev/null
        pkill -f show_scheduler.py 2>/dev/null
        sleep 1
        python3 "$BRIDGE"    >> /home/fpp/media/logs/xr18_bridge.log    2>&1 &
        python3 "$SCHEDULER" >> /home/fpp/media/logs/showmanager.log     2>&1 &
        ;;
esac

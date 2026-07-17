#!/bin/bash
# FPP runs <plugin>/scripts/postStart.sh after fppd starts.
# Launch the ShowManager daemons.
PLUGIN_DIR="$(cd "$(dirname "$0")/.." && pwd)"

pkill -f xr18_bridge.py    2>/dev/null
pkill -f show_scheduler.py 2>/dev/null
sleep 1

python3 "$PLUGIN_DIR/Scripts/xr18_bridge.py"    >> /home/fpp/media/logs/xr18_bridge.log  2>&1 &
python3 "$PLUGIN_DIR/Scripts/show_scheduler.py" >> /home/fpp/media/logs/showmanager.log  2>&1 &

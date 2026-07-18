#!/bin/bash
# Single source of truth for (re)starting the ShowManager daemons.
# Always stops any running copies FIRST, then launches the current code — so
# it's safe to call on boot (postStart.sh) and on plugin update (fpp_install.sh)
# without ever leaving two copies running.
#
# The daemons also hold their own flock single-instance locks, so a racing
# launch can't duplicate them; this kill-first step is what swaps OLD code for
# NEW after an update (the lock alone won't, since the old process keeps
# running its old code until stopped).

PLUGIN_DIR="$(cd "$(dirname "$0")/.." && pwd)"
LOGDIR="/home/fpp/media/logs"

pkill -f xr18_bridge.py    2>/dev/null
pkill -f show_scheduler.py 2>/dev/null
sleep 1   # let the processes exit and release their locks before relaunch

python3 "$PLUGIN_DIR/Scripts/xr18_bridge.py"    >> "$LOGDIR/xr18_bridge.log" 2>&1 &
python3 "$PLUGIN_DIR/Scripts/show_scheduler.py" >> "$LOGDIR/showmanager.log" 2>&1 &

#!/bin/bash
# FPP runs <plugin>/scripts/postStart.sh after fppd starts.
# (Re)launch the ShowManager daemons — kill-first, so a boot never duplicates.
exec "$(dirname "$0")/restart_daemons.sh"

# Troubleshooting

## First: read the log

The **Status** tab shows a live tail of the scheduler log with pause/copy/clear. Or from SSH:

```bash
tail -50 /home/fpp/media/logs/showmanager.log
tail -50 /home/fpp/media/logs/xr18_bridge.log
```

Errors are labelled `ERROR`, warnings `WARNING`.

---

## Shows / scheduling

### A show doesn't trigger

- **Scheduler running?** `pgrep -af show_scheduler.py` — expect exactly one process.
- **On today's schedule?** `grep "$(date +%F)" /home/fpp/media/config/ShowManagerSchedule.config` (repeating rules are expanded at runtime, so also confirm the rule's day/date range covers today).
- **Blackout or disabled?** A whole-day blackout, a timed blackout covering the show, or an active kiosk "disable" all suppress shows. Check `ShowManagerSchedule.config` for `"type":"blackout"` and `ShowManagerOverrides.config` for `disabled_until`.
- **In the log:** `grep -E "Show starting|Show ended|blackout|disabled" /home/fpp/media/logs/showmanager.log`.

### Everything fires twice (announcements, shows)

Two scheduler copies were running. A single-instance lock now prevents this — restart the daemons once (**Restart Scheduler**) so the old duplicate is cleared and one locked instance takes over. Confirm with `pgrep -af show_scheduler.py` (should be one line).

### A show starts but ends instantly

The scheduler tracks the show **by playlist name**. Confirm the scheduled playlist name matches FPP exactly (case-sensitive) and plays cleanly when started from FPP directly.

### "FPP Scheduler is disabled" warning in FPP

FPP adds that warning once at boot when its own scheduler is off. The plugin re-enables it automatically when FPP's native schedule is empty; the warning then clears on the next FPPD restart. If FPP's native schedule still has entries, the plugin leaves it alone (to avoid double-scheduling) and logs why — clear those entries in FPP's Scheduler page.

---

## Background music / effects

- **Not starting?** Background music runs only when FPP is idle, inside its window, and not blacked-out/disabled/stopped. The **Status** tab's System card shows BG Music / BG Effect as green (active) or amber (idle). The Background tab windows may simply be closed right now.
- **Effect not appearing?** Effects use FPP's `Effect Start` / `FSEQ Effect Start` commands. During a window, watch the log — a rejected command is logged. Confirm the selected `.eseq`/`.fseq` exists in FPP.
- **Stopped and won't come back?** A manual **Stop** keeps background down until the next scheduled show (that's intentional — use it as "quiet now"). The kiosk **Disable system** keeps everything off until it expires or you re-enable.

---

## Announcements

### Don't play

- `which ffmpeg` (or `mpg123`); install with `sudo apt install -y ffmpeg`.
- File present? Check the folder, or that a pre-show row's absolute path still exists (the UI marks missing files).
- ALSA output points at the XR18? `aplay -L | grep default`.
- Log: `grep -i announcement /home/fpp/media/logs/showmanager.log`.

### Play but music doesn't duck

The fader-state file may not exist yet:

```bash
cat /tmp/xr18_current_fader
```

If empty, the scheduler falls back to FPP's current volume. Wait for the bridge to write one update, and confirm the mixer IP/channel in the Hardware tab.

### Fire during a show

Shouldn't happen — the scheduler checks its own in-show flag and FPP's live status. If a show is audio-only (no FSEQ), FPP reports an empty `current_sequence` and it can look idle; add a minimal FSEQ to the show playlist so FPP reports it.

---

## Brightness fade

- Requires **fpp-brightness**: `curl -s http://localhost/api/plugin-apis/Brightness/100` should succeed.
- The dim is a *fade to a level, then a snap to normal at show start* — a brief dip-then-reveal, not a steady dim. See [announcements.md](announcements.md#pre-show-brightness-fade).
- Log: `grep -i brightness /home/fpp/media/logs/showmanager.log`.

---

## XR18 volume sync

### Fader doesn't follow FPP volume

- Bridge running? `pgrep -af xr18_bridge.py`.
- Correct IP? The bridge logs its target on startup: `grep "Bridge starting" /home/fpp/media/logs/xr18_bridge.log`. Fix it on the Hardware tab (saving restarts the bridge).
- Reachable? `ping <xr18-ip>`. On event Wi-Fi, disable AP/client isolation. UDP 10024 (out) / 10023 (in) must be open.

### Hardware fader doesn't update FPP

The XR18 only sends updates to whichever host last sent `/xremote`; the bridge re-sends it every 9 s. If the subscription lapsed (XR18 power-cycled), check for xremote entries in the bridge log.

### Volume stuck

A stale pause-sync flag from a crashed announcement:

```bash
ls -la /tmp/xr18_pause_sync   # watchdog removes it after 5 min; else: rm /tmp/xr18_pause_sync
```

---

## Kiosk 3D preview / garland

Both need **fpp-plugin-3DViewer** at `/3dviewer/`. If the preview card doesn't appear, the plugin isn't installed there. Add `&garlanddebug=1` to the kiosk URL for an on-screen readout of the garland's data/feed/model state. The kiosk auto-reconnects the feed and reloads the preview periodically, so brief drops self-heal.

---

## Daemon management

Restart via **Plugin Setup → Restart Daemons** or the **Restart Scheduler** button. Manually:

```bash
pkill -f show_scheduler.py; pkill -f xr18_bridge.py
sleep 1
python3 /home/fpp/media/plugins/fpp-ShowManager/Scripts/xr18_bridge.py    >> /home/fpp/media/logs/xr18_bridge.log 2>&1 &
python3 /home/fpp/media/plugins/fpp-ShowManager/Scripts/show_scheduler.py >> /home/fpp/media/logs/showmanager.log 2>&1 &
```

Clear coordination state (does not touch config):

```bash
pkill -f show_scheduler.py; pkill -f xr18_bridge.py
rm -f /tmp/xr18_pause_sync /tmp/xr18_current_fader /tmp/showmanager_manual_stop /tmp/showmanager_scheduler.lock
```

Reset a playlist rotation: `rm /home/fpp/media/config/ShowManagerRotation.config`.

---

## Log rotation

The logs grow indefinitely. To truncate daily, add to `crontab -e`:

```bash
0 4 * * * truncate -s 0 /home/fpp/media/logs/showmanager.log /home/fpp/media/logs/xr18_bridge.log
```

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

Two scheduler copies were running. Both daemons now hold a single-instance `flock` lock, so a second copy exits on startup — and a plugin **update** stops the old daemons before starting the new code (`fpp_install.sh`), so updates no longer leave duplicates. If you still see two, restart the daemons once (**Restart Scheduler**, or Plugin Setup → Restart Daemons) and confirm with `pgrep -af show_scheduler.py` / `pgrep -af xr18_bridge.py` (one line each).

### "Permission denied" on the lock file when restarting

The daemons keep a single-instance lock at `/tmp/showmanager_scheduler.lock` (and `…_bridge.lock`). If the file was created by one user (root, at boot) and the restart runs as another (the web user), older versions crashed opening it. The lock now falls back to a read-only handle in that case (still enough to enforce single-instance) and creates the file world-writable, so this self-heals — a reboot clears the stale file entirely. If you ever want to force it: as root, `rm -f /tmp/showmanager_*.lock` then restart.

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

## Now-playing shows a filename, not the song title

The title/artist come from the audio file's tags via `ffprobe` (part of ffmpeg). If it shows the bare filename instead:

- The file has no title/artist tags — add them (any tag editor), or the filename is the fallback by design.
- `ffprobe` isn't installed — `command -v ffprobe` (install ffmpeg: `sudo apt install -y ffmpeg`).
- FPP reports no media file for the current item (audio embedded in the FSEQ) — there's nothing to read tags from; the sequence name is shown instead.

---

## Kiosk 3D preview / garland

Both need **fpp-plugin-3DViewer** at `/3dviewer/`. If the preview card doesn't appear, the plugin isn't installed there. Add `&garlanddebug=1` to the kiosk URL for an on-screen readout of the garland's data/feed/model state. The kiosk auto-reconnects the feed and reloads the preview periodically, so brief drops self-heal.

---

## Daemon management

Restart via **Plugin Setup → Restart Daemons** or the **Restart Scheduler** button. Manually, use the bundled kill-first launcher (the same one boot and updates use):

```bash
/home/fpp/media/plugins/fpp-ShowManager/scripts/restart_daemons.sh
```

It stops any running copies, waits for their locks to release, then starts the current code — so it never duplicates. (Boot runs `scripts/postStart.sh` and plugin updates run `fpp_install.sh`; both call this script.)

Clear coordination state (does not touch config):

```bash
pkill -f show_scheduler.py; pkill -f xr18_bridge.py
rm -f /tmp/xr18_pause_sync /tmp/xr18_current_fader /tmp/showmanager_manual_stop \
      /tmp/showmanager_run_now /tmp/showmanager_cloud_backup_day \
      /tmp/showmanager_scheduler.lock /tmp/showmanager_bridge.lock
```

Reset a playlist rotation: `rm /home/fpp/media/config/ShowManagerRotation.config`.

---

## Log rotation

Both logs rotate automatically — each is capped at ~2 MB with 3 old copies kept (`showmanager.log.1`, `.2`, …), so they can't fill the SD card. No cron job is needed. (Nothing stops you truncating them manually: `truncate -s 0 /home/fpp/media/logs/showmanager.log`.)

---

## System tab (diagnostics, backup)

The **System** tab has two tools for keeping a rig healthy:

- **Diagnostics** — a one-click health check: scheduler/bridge/FPP alive, mixer reachable (ping), brightness and 3D-viewer plugins present, an audio player installed, the clock NTP-synced (a wrong clock fires shows at the wrong time), free disk, and a writable config dir. **Flash lights** pulses brightness 0 → 100 % so you can confirm the LEDs respond. Run it before an event.
- **Backup & Restore** — **Download backup** saves every `ShowManager*.config` (except the Dropbox secrets) as one JSON file; **Restore from file** writes them back and restarts the daemons. Take a backup before big schedule changes and before moving to a new Pi.
- **Dropbox Backup** — upload backups to your Dropbox on demand (**Back up now**) or automatically each night (**Nightly auto-backup**, runs after 04:00). One-time setup: create a Dropbox app, save its App key & secret, **Authorize**, paste the code, **Connect**.

If a check reads **Fail**/**Check**, the detail column says why (e.g. "No ping response" → fix the mixer IP on the Hardware tab; "Clock sync: NOT synced" → the Pi needs network time).

### Dropbox backup fails

- **"Not connected"** — finish the Authorize → paste code → Connect steps; a saved App key/secret alone isn't enough.
- **Upload/refresh errors** — the Pi needs outbound HTTPS. Re-run **Test**; if the app's access was revoked in Dropbox, **Disconnect** and reconnect. The app must have the `files.content.write` scope.
- **Nightly backup didn't run** — it fires once daily after 04:00 and only if the scheduler is running and auto-backup is on. Check `grep -i dropbox /home/fpp/media/logs/showmanager.log`.

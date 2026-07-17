# Troubleshooting

## First step for any problem — check the logs

```bash
tail -50 /home/fpp/media/logs/xr18_bridge.log
tail -50 /home/fpp/media/logs/showmanager.log
```

Both logs include timestamps. Errors are labelled `ERROR`, warnings `WARNING`.

---

## Volume sync problems

### XR18 fader doesn't respond when FPP volume changes

**Check the bridge is running:**
```bash
ps aux | grep xr18_bridge.py
```

If no process is listed, restart it:
```bash
python3 /home/fpp/media/plugins/ShowManager/Scripts/xr18_bridge.py &
```

**Check the XR18 IP:**

The bridge logs its target IP on startup:
```
2024-12-01 19:00:00 INFO XR18 Bridge starting — XR18 192.168.1.50, music ch01/ch02 ...
```

If wrong, update the IP in the Hardware settings page and restart the bridge.

**Verify network reachability:**
```bash
ping 192.168.1.50          # replace with your XR18 IP
```

If ping fails, check that the FPP host and XR18 are on the same subnet. On dedicated event Wi-Fi networks, client isolation (AP isolation) can block device-to-device UDP — disable it on your access point.

**Check firewall:**
```bash
sudo iptables -L -n | grep -E "10023|10024"
```

UDP ports 10024 (out) and 10023 (in) must be open.

---

### Moving the XR18 hardware fader doesn't update FPP volume

The XR18 only sends fader updates to whichever host last sent `/xremote`. The bridge sends `/xremote` every 9 seconds to maintain the subscription. If it's been more than 10 seconds since the bridge started (or the XR18 was power-cycled), the subscription may have lapsed.

Check the bridge log for xremote heartbeat entries. If they're missing, the bridge may be failing to send OSC packets — check the XR18 IP and network connectivity.

---

### Volume is stuck — fader won't move even though the bridge is running

The pause-sync flag may be stuck from a crashed announcement:

```bash
ls -la /tmp/xr18_pause_sync
```

If the file exists and is more than a few minutes old, the watchdog should have removed it. If not:

```bash
rm /tmp/xr18_pause_sync
```

The bridge resumes normal sync within 2 seconds.

---

## Show scheduling problems

### Shows aren't triggering at the scheduled time

**Check the scheduler is running:**
```bash
ps aux | grep show_scheduler.py
```

**Check today's schedule is loaded:**
```bash
grep "$(date +%Y-%m-%d)" /home/fpp/media/config/ShowManagerSchedule.config
```

If the date isn't there, add the show in the Schedule page.

**Check for a blackout entry:**
```bash
grep "blackout" /home/fpp/media/config/ShowManagerSchedule.config
```

If today is a blackout day, all shows are suppressed. Open that date in the Schedule page and click **Remove Blackout**.

**Check the scheduler log for the event:**
```bash
grep "Show starting\|Show ended\|skip" /home/fpp/media/logs/showmanager.log
```

---

### Show triggers but immediately ends

FPP returned to idle faster than expected. Check:

1. The playlist name in Shows matches exactly what FPP has (case-sensitive)
2. The playlist exists and plays without errors in FPP directly
3. FPP isn't in a test/disabled state

Try starting the playlist manually from the FPP web interface to confirm it works.

---

### Background music doesn't resume after a show

Check `background_playlist` in the Announcements settings page — if it's blank, nothing starts. Make sure the name matches exactly the FPP playlist name (case-sensitive).

```bash
grep "background_playlist" /home/fpp/media/config/ShowManagerAnnouncements.config
```

Also check the scheduler log:
```bash
grep "Resuming background" /home/fpp/media/logs/showmanager.log
```

---

## Announcement problems

### Announcements don't play

**Check `ffmpeg` is installed:**
```bash
which ffmpeg
ffmpeg -version
```

If not: `sudo apt install -y ffmpeg`

**Check the audio file exists:**
```bash
ls /home/fpp/media/plugins/ShowManager/announcements/
```

**Check the ALSA default device is the XR18:**
```bash
aplay -L | grep default
```

The announcement is played to the ALSA `default` device. If FPP's audio is going to the XR18, the default device should already point there. If not, set it in `/etc/asound.conf` or FPP's audio settings.

**Check the scheduler log:**
```bash
grep -i "announcement\|audio" /home/fpp/media/logs/showmanager.log
```

---

### Announcement plays but music doesn't duck

The fader state file may be missing (bridge hasn't run long enough to write it yet):

```bash
cat /tmp/xr18_current_fader
```

If empty or missing, the scheduler falls back to computing the fader from FPP's current volume. Wait for the bridge to write at least one fader update (it writes on every volume change), then test again.

Also confirm the XR18 IP and channel numbers match what's in the hardware config — the scheduler sends OSC independently of the bridge using those same values.

---

### Announcements fire during a show

This should not happen — the scheduler checks both its own `_in_show` flag and FPP's live status. If it does:

1. Check the scheduler log for `"Show running — skipping"` entries — they should appear
2. Confirm `current_sequence` is non-empty in FPP status while the show plays:
   ```bash
   curl -s http://localhost/api/fppd/status | python3 -m json.tool | grep current_sequence
   ```
3. If FPP shows an empty `current_sequence` while a show is playing, your show may be audio-only (no FSEQ). In this case the scheduler can't distinguish it from background music. Add a minimal dummy FSEQ to your show playlist to make FPP report it correctly.

---

## fpp-brightness problems

### Lighting doesn't dim before shows

Check fpp-brightness is installed:
```bash
curl -s http://localhost/api/plugin-apis/Brightness/100
```

If this returns an error, the plugin isn't running. Install it via FPP Plugin Manager.

Check the scheduler log:
```bash
grep -i "brightness\|dim" /home/fpp/media/logs/showmanager.log
```

Verify the `pre_show_brightness` value is set in Announcements settings (default: `20`).

---

## Daemon management

### Restart both daemons

```bash
pkill -f xr18_bridge.py
pkill -f show_scheduler.py
sleep 1
python3 /home/fpp/media/plugins/ShowManager/Scripts/xr18_bridge.py \
    >> /home/fpp/media/logs/xr18_bridge.log 2>&1 &
python3 /home/fpp/media/plugins/ShowManager/Scripts/show_scheduler.py \
    >> /home/fpp/media/logs/showmanager.log 2>&1 &
```

Or re-save any settings page in the FPP UI — that triggers `plugin_setup.php` which does the same thing.

### Make daemons start on FPP boot

FPP runs `plugin_setup.php` when FPP starts if the plugin is enabled, so the daemons start automatically. If they don't, check that the plugin is enabled in FPP Plugin Manager.

### Clear all plugin state and start fresh

```bash
pkill -f xr18_bridge.py
pkill -f show_scheduler.py
rm -f /tmp/xr18_pause_sync /tmp/xr18_current_fader
```

This wipes the coordination files without touching your config. The daemons start clean on next launch.

---

## Rotation state got confused

If shows are alternating in the wrong order, reset the rotation state:

```bash
rm /home/fpp/media/config/ShowManagerRotation.config
```

The file is recreated automatically. The first show in the rotation list will play next.

---

## Log rotation

The log files grow indefinitely. To rotate them:

```bash
# Add to crontab (crontab -e):
0 4 * * * truncate -s 0 /home/fpp/media/logs/xr18_bridge.log \
    /home/fpp/media/logs/showmanager.log
```

This clears both logs at 4 AM daily.

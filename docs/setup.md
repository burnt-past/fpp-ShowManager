# Setup Guide

## Prerequisites

- **FPP 9.4 or later** on a Linux host
- `ffmpeg` (or `mpg123`) for announcement playback
- **fpp-brightness** plugin — optional, for the pre-show brightness fade
- **Behringer XR18** on the same LAN — optional, for volume sync/ducking
- **fpp-plugin-3DViewer** — optional, for the kiosk's live 3D preview and show-synced garland

Everything except FPP itself is optional; features whose hardware/plugins are absent simply no-op.

---

## Step 1 — Install audio playback

SSH into the FPP host:

```bash
sudo apt update
sudo apt install -y ffmpeg     # or: sudo apt install -y mpg123
```

---

## Step 2 — Install the plugin

1. FPP web UI → **Content Setup → Plugin Manager**
2. Install **Show Manager** (repo `burnt-past/fpp-ShowManager`)

The daemons (`xr18_bridge.py`, `show_scheduler.py`) start automatically on boot via `scripts/postStart.sh` — the hook FPP runs after fppd starts.

**After installing or updating, restart the daemons once** so the new code takes over: **Plugin Setup → Restart Daemons**, the **Restart Scheduler** button on the Status tab, or a reboot.

---

## Step 3 — (Optional) fpp-brightness

For the pre-show brightness fade, install **fpp-brightness** from the Plugin Manager. No extra configuration is needed. Without it, the brightness fade simply does nothing.

---

## Step 4 — (Optional) Connect the XR18

For volume sync and announcement ducking:

**Network** — the XR18 must be reachable over UDP. Find its IP in *X AIR Edit* and put the FPP host on the same subnet. On dedicated event Wi-Fi, disable AP/client isolation so device-to-device UDP works.

**USB audio** — connect the FPP host to the XR18's USB port and confirm the device:

```bash
aplay -l          # look for "XR18" / "X18"
```

Set it as FPP's audio output in **FPP → Settings**. In X AIR Edit, route **USB Return** to your music channel(s) and assign them to the main LR mix.

**Configure** — open the **Hardware** tab and set the mixer IP, music fader channel, show/idle levels, and announce channel/level. Saving restarts the bridge automatically.

---

## Step 5 — Build your schedule

Open **Show Manager** in the FPP menu. Work through the tabs:

- **Schedule** — click a day to add a one-off show (pick an FPP playlist and time), or add a repeating rule (days, window, interval). Mark whole-day or timed blackouts.
- **Background** — set the background music playlist + window, and the background effect (`.eseq`/`.fseq`) + window.
- **Announcements** — add pre-show announcement rows, daytime announcements, ducking, and the pre-show brightness fade. Upload audio here or drop files in the folders below.
- **Status** — watch it run, trigger a playlist manually, and read the scheduler log.

The scheduler reads the calendar every 30 seconds — no restart needed after schedule changes.

### Announcement audio locations

```
/home/fpp/media/plugins/fpp-ShowManager/announcements/         ← pre-show
/home/fpp/media/plugins/fpp-ShowManager/announcements/daytime/ ← daytime
```

These folders are created automatically. Pre-show rows can also point at any audio already in FPP's `music/` or `upload/` folders.

---

## Step 6 — (Optional) Kiosk

The **Kiosk** button (top-right of the plugin) opens a full-screen, touch-first page for a wall-mounted tablet: giant show state, one-tap start, hold-to-stop, temporary volume, "disable system", a live 3D preview, and a light string that mirrors the running show. Bookmark it on the tablet:

```
http://<fpp-host>/plugin.php?plugin=fpp-ShowManager&page=kiosk.php&nopage=1
```

The 3D preview and show-synced garland appear only if **fpp-plugin-3DViewer** is installed; everything else works without it.

---

## Folder & file layout

```
/home/fpp/media/plugins/fpp-ShowManager/
├── app.php                 ← operator UI (all tabs)
├── kiosk.php               ← wall-tablet kiosk
├── ajax.php                ← UI backend
├── scripts/postStart.sh    ← starts the daemons on boot
├── Scripts/
│   ├── xr18_bridge.py       ← XR18 OSC volume sync
│   └── show_scheduler.py    ← scheduler / background / announcements
└── announcements/…          ← uploaded audio

/home/fpp/media/config/      ← ShowManager*.config (see configuration.md)
/home/fpp/media/logs/        ← showmanager.log, xr18_bridge.log
```

---

## Verifying the daemons

```bash
pgrep -af "xr18_bridge|show_scheduler"
tail -f /home/fpp/media/logs/showmanager.log
```

You should see exactly **one** of each process (a single-instance lock prevents duplicates). To restart, use the **Restart Daemons** / **Restart Scheduler** buttons, or:

```bash
pkill -f show_scheduler.py; pkill -f xr18_bridge.py
sleep 1
python3 /home/fpp/media/plugins/fpp-ShowManager/Scripts/xr18_bridge.py    >> /home/fpp/media/logs/xr18_bridge.log 2>&1 &
python3 /home/fpp/media/plugins/fpp-ShowManager/Scripts/show_scheduler.py >> /home/fpp/media/logs/showmanager.log 2>&1 &
```

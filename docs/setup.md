# Setup Guide

## Prerequisites

Before installing this plugin you need:

- **FPP 5.0 or later** running on a Linux machine
- **Behringer XR18** on the same local network as the FPP host (Ethernet or Wi-Fi)
- **fpp-brightness plugin** installed (for pre-show lighting dim/restore)
- The FPP host connected to the XR18 via **USB** for audio output
- `ffmpeg` installed for announcement playback

---

## Step 1 — Install dependencies

SSH into your FPP host:

```bash
sudo apt update
sudo apt install -y ffmpeg
```

Verify it installed:

```bash
ffmpeg -version
```

If `ffmpeg` is unavailable on your image, `mpg123` is used as a fallback:

```bash
sudo apt install -y mpg123
```

---

## Step 2 — Install the plugin via FPP Plugin Manager

1. Open the FPP web interface in your browser
2. Go to **Content Setup → Plugin Manager**
3. Search for **Show Manager** or paste the repository URL:
   `https://github.com/burnt-past/fpp-ShowManager`
4. Click **Install**
5. FPP will clone the repo and run `plugin_setup.php`, which starts both background daemons

---

## Step 3 — Install fpp-brightness

If not already installed:

1. In FPP Plugin Manager, search for **fpp-brightness**
2. Install it — no additional configuration is needed for this plugin to work with ours

---

## Step 4 — Connect and configure the XR18

### Network connection

The XR18 must be reachable from the FPP host over UDP. Find its IP address in **X AIR Edit** (the Behringer control app) under the network status screen.

Make sure your FPP host and XR18 are on the **same subnet**. A dedicated Wi-Fi network for the XR18 (common in live setups) works as long as the FPP host can reach it.

### USB audio connection

Plug a USB cable from the FPP host to the XR18's USB port. The XR18 will appear as an ALSA audio device. To confirm:

```bash
aplay -l
# Look for "XR18" or "X18" in the output
```

Set this device as FPP's audio output in **FPP → Settings → Audio**.

### XR18 internal routing

In X AIR Edit, route **USB Return 1-2** to the channels you intend to use for music. By default this plugin assumes:

- **Channel 01** — music left
- **Channel 02** — music right

Both channels should be assigned to the main LR mix.

---

## Step 5 — Configure the plugin

Open **FPP → Plugins → Show Manager — Hardware**:

| Field | Value |
|---|---|
| XR18 IP Address | IP shown in X AIR Edit (e.g. `192.168.1.50`) |
| Music Channel 1 | `01` (or whichever XR18 channel your music left goes to) |
| Music Channel 2 | `02` |
| Announcement Channel | `03` (optional — only relevant if you have a separate input there) |
| Announcement Channel Volume | `0.75` — the fader level maintained on the announcement channel |

Click **Save Settings**.

---

## Step 6 — Define your shows

Go to **Show Manager — Shows**:

1. Click **+ Add Show** for each show you have
2. Give it an ID (no spaces, e.g. `show_a`), a display name, and select its FPP playlist from the dropdown
3. Optionally select a **pre-show transition playlist** — an FPP sequence you've created that fades or dims the lighting before the main show begins
4. Set the **transition duration** (seconds) — how long to run the transition before starting the main show
5. Set the **approximate duration** (minutes) — used as a timeout safety net; make it 1.5–2× your actual show length
6. Click **Save Shows**

---

## Step 7 — Configure announcements

Go to **Show Manager — Announcements**:

### Place your audio files

```
/home/fpp/media/plugins/ShowManager/announcements/
    5min.mp3              ← pre-show warning files (any name you choose)
    10min.mp3
    15min.mp3
    daytime/
        ad_01.mp3         ← general/daytime announcement files
        ad_02.mp3
        ...
```

The folders are created automatically when the plugin loads. Copy files in via SFTP or `scp`.

### Settings

- **Duck level** — how quiet the music gets while an announcement plays (0.25 = 25% of current level)
- **Fade duration** — how many seconds the fade down/up takes (2–3 seconds feels natural)
- **Gain boost** — how much louder the announcement audio is relative to the music (6 dB ≈ 2× volume)
- **Pre-show brightness** — the fpp-brightness value while a show is starting (0 = fully off, 100 = normal)
- **Normal brightness** — restored after each show ends
- **Background playlist** — the FPP playlist that plays (looping) between shows
- **Pre-show announcements** — add one row per warning; set minutes before show and filename

---

## Step 8 — Schedule your shows

Go to **Show Manager — Schedule**:

1. Navigate to the current month using **Prev / Next**
2. Click on any day to open the day editor
3. Click **Add Show to Day**, set the time, choose the show (or set up a rotation)
4. To mark a date as a **blackout** (no shows), click **Mark as Blackout** in the day editor

The scheduler reads this calendar every 30 seconds. No restart is needed after making schedule changes.

---

## Folder structure

```
/home/fpp/media/plugins/ShowManager/
│
├── Scripts/
│   ├── xr18_bridge.py          ← XR18 OSC volume sync daemon
│   └── show_scheduler.py       ← Show/announcement scheduler daemon
│
├── announcements/
│   ├── 5min.mp3                ← Your pre-show announcement files
│   ├── 10min.mp3
│   └── daytime/
│       └── *.mp3               ← General daytime announcement files
│
├── config.php                  ← Hardware settings page
├── shows.php                   ← Show definitions page
├── schedule.php                ← Calendar page
├── announcements.php           ← Announcement settings page
├── plugin_setup.php            ← Starts both daemons on plugin load
└── pluginInfo.json

/home/fpp/media/config/
├── ShowManager.config    ← Hardware settings (JSON)
├── ShowManagerShows.config            ← Show definitions (JSON)
├── ShowManagerSchedule.config         ← Calendar entries (JSON)
├── ShowManagerAnnouncements.config    ← Announcement settings (JSON)
└── ShowManagerRotation.config    ← Auto-managed rotation state (JSON)

/home/fpp/media/logs/
├── xr18_bridge.log             ← Volume sync log
└── showmanager.log          ← Scheduler / announcement log
```

---

## Verifying the daemons are running

```bash
ps aux | grep -E "xr18_bridge|show_scheduler"
```

You should see two Python processes. If not, check the logs:

```bash
tail -f /home/fpp/media/logs/xr18_bridge.log
tail -f /home/fpp/media/logs/showmanager.log
```

To restart manually:

```bash
pkill -f xr18_bridge.py
pkill -f show_scheduler.py
python3 /home/fpp/media/plugins/ShowManager/Scripts/xr18_bridge.py &
python3 /home/fpp/media/plugins/ShowManager/Scripts/show_scheduler.py &
```

Or simply disable and re-enable the plugin in FPP's Plugin Manager, which re-runs `plugin_setup.php`.

# FPP Show Manager

A [Falcon Pi Player](https://github.com/FalconChristmas/fpp) plugin that integrates a **Behringer XR18** digital mixer with FPP for complete show management — volume sync, scheduled shows, pre-show announcements, lighting transitions, and background music automation.

---

## Features

- **Bidirectional volume sync** — FPP master volume ↔ XR18 channel faders via OSC. Moving a hardware fader on the XR18 updates FPP, and vice versa.
- **Calendar-based show scheduler** — schedule shows by date, not just day-of-week. Supports blackout dates (client requests, holidays, etc.) and rotation between multiple playlists so shows never repeat back-to-back.
- **Pre-show announcements** — plays MP3 audio at configurable intervals before each show (e.g. 15 min, 10 min, 5 min warnings). Music ducks automatically while the announcement plays.
- **Daytime general announcements** — plays random MP3s from a folder on a repeating interval during configurable hours, suppressed near show times.
- **Lighting transitions** — sends dim/restore commands to the [fpp-brightness](https://github.com/FalconChristmas/fpp-brightness) plugin before and after each show.
- **Background music automation** — resumes a configured looping playlist automatically after every show ends.
- **Show-safe** — all announcements and fader changes are completely suppressed while an FPP sequence (`.fseq`) is actively running. The plugin hands off fully to FPP during shows.
- **Crash-safe** — every thread catches its own exceptions. A watchdog cleans up stale coordination flags so the volume bridge always recovers.

---

## Requirements

| Requirement | Notes |
|---|---|
| Falcon Pi Player ≥ 5.0 | Running on a Linux machine |
| Behringer XR18 (X Air 18) | Connected to the same network as the FPP host |
| [fpp-brightness](https://github.com/FalconChristmas/fpp-brightness) plugin | For pre-show lighting dim/restore |
| Python 3.7+ | Pre-installed on FPP images |
| `ffmpeg` **or** `mpg123` | For announcement audio playback (`sudo apt install ffmpeg`) |
| XR18 connected via USB | For audio output from FPP to the mixer |
| FPP host and XR18 on the same LAN | OSC control travels over UDP, not USB |

---

## How It Works

```
┌─────────────────────────────────────────────────────────────────┐
│                        FPP Host (Linux)                          │
│                                                                  │
│  ┌──────────────┐   OSC fader sync    ┌──────────────────────┐  │
│  │ xr18_bridge  │◄───────────────────►│                      │  │
│  │   .py        │                     │   Behringer XR18     │  │
│  │              │   /xremote hbeat    │   UDP port 10024     │  │
│  │ Volume sync  │────────────────────►│                      │  │
│  └──────┬───────┘                     │   ch1/ch2: music     │  │
│         │ pause-sync flag             │   ch3:     announce  │  │
│         │ fader state file            └──────────────────────┘  │
│  ┌──────▼───────────────────────────────────────────┐           │
│  │              show_scheduler.py                    │           │
│  │                                                   │           │
│  │  ┌─────────────┐  ┌──────────────┐  ┌─────────┐  │           │
│  │  │  Schedule   │  │  Daytime     │  │Watchdog │  │           │
│  │  │  loop       │  │  announcer   │  │         │  │           │
│  │  │  (30s tick) │  │  (60s tick)  │  │(60s)    │  │           │
│  │  └──────┬──────┘  └──────┬───────┘  └─────────┘  │           │
│  └─────────┼────────────────┼──────────────────────--┘           │
│            │                │                                    │
│     FPP HTTP API      ALSA audio out                            │
│     Brightness API    (USB → XR18)                              │
└─────────────────────────────────────────────────────────────────┘
```

### Signal flow for an announcement

1. Scheduler detects it's time for an announcement and no show is running
2. Writes `PAUSE_SYNC_FLAG` → bridge stops syncing faders
3. Reads current fader level from `FADER_STATE_FILE`
4. Fades XR18 music channel faders down to duck level over 2–3 seconds via OSC
5. Plays the MP3 via `ffmpeg` with a software gain boost (+6 dB default)
6. Fades faders back up to original level
7. Removes `PAUSE_SYNC_FLAG` → bridge resumes normal sync

### Signal flow for a show

1. Scheduler fires at the scheduled time
2. Calls `GET /api/plugin-apis/Brightness/{pre_show_level}` to dim lighting
3. Calls FPP API to start the show playlist
4. Polls FPP status every 5 seconds until the sequence goes idle
5. Calls `GET /api/plugin-apis/Brightness/{normal_level}` to restore lighting
6. Calls FPP API to start the background music playlist (looping)

---

## Installation

See **[docs/setup.md](docs/setup.md)** for the full installation guide.

Quick version:

```bash
# Install the plugin via FPP Plugin Manager
# Plugin name: Show Manager
# Repo: https://github.com/burnt-past/fpp-ShowManager

# Install ffmpeg for announcement playback
sudo apt install -y ffmpeg

# Place announcement MP3s here:
# /home/fpp/media/plugins/ShowManager/announcements/       ← pre-show
# /home/fpp/media/plugins/ShowManager/announcements/daytime/ ← general
```

---

## Plugin Pages

After installation the FPP menu gains four entries:

| Page | Purpose |
|---|---|
| **Hardware** | XR18 IP address, music channel numbers, announcement channel volume |
| **Shows** | Define each show: FPP playlist, transition playlist, estimated duration |
| **Schedule** | Monthly calendar — add shows to dates, mark blackout days |
| **Announcements** | Duck levels, gain boost, brightness values, pre-show timing, daytime window |

---

## Log Files

| File | Contains |
|---|---|
| `/home/fpp/media/logs/xr18_bridge.log` | Volume sync, XR18 OSC activity |
| `/home/fpp/media/logs/showmanager.log` | Show triggers, announcements, errors |

---

## Configuration Files

All stored in `/home/fpp/media/config/`:

| File | Contents |
|---|---|
| `ShowManager.config` | Hardware: IP, channels, duck level, fade time |
| `ShowManagerShows.config` | Show definitions (playlists, durations) |
| `ShowManagerSchedule.config` | Calendar entries (shows + blackout dates) |
| `ShowManagerAnnouncements.config` | Announcement and brightness settings |
| `ShowManagerRotation.config` | Auto-managed rotation index per slot |

---

## Screenshots

> Screenshots below are from a live installation. See [`docs/screenshots/`](docs/screenshots/) for full-size versions.

| | |
|---|---|
| ![Schedule Calendar](docs/screenshots/schedule.png) | ![Show Definitions](docs/screenshots/shows.png) |
| *Monthly calendar — click any day to schedule shows or mark blackout* | *Show definition page — select FPP playlists and set durations* |
| ![Announcements](docs/screenshots/announcements.png) | ![Hardware Settings](docs/screenshots/hardware.png) |
| *Announcement settings — ducking, brightness, pre-show and daytime config* | *Hardware page — XR18 IP and channel configuration* |

---

## Author

Alexander Woolum — [adeptevents.com](https://adeptevents.com)

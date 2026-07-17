# FPP Show Manager

A [Falcon Pi Player](https://github.com/FalconChristmas/fpp) plugin for running a scheduled outdoor light‑and‑music show. It adds a calendar scheduler, background music and lighting automation, pre‑show announcements, bidirectional volume sync with a **Behringer XR18** mixer, and a full‑screen **kiosk** for wall‑mounted tablets — including a live 3D preview and a page that changes color with the show.

Targets **FPP 9.4+**.

---

## Two interfaces

**Show Manager** (in the FPP menu) — the operator UI, styled to blend with FPP. Five tabs:

| Tab | What it does |
|---|---|
| **Status** | Live now‑playing, FPP/XR18 meters, manual trigger (start/stop any playlist), today's schedule, system health (daemons, background music/effect), and the scheduler log. |
| **Schedule** | Month/Week/Day calendar. One‑off shows, repeating rules, and blackouts (whole‑day or a time range). Background music/effect windows are shown on the calendar too. |
| **Background** | Two independent daily schedules — background **music** (a looping playlist) and a background **effect** (a `.eseq` or `.fseq` overlay), each with its own window. |
| **Announcements** | Pre‑show announcements, daytime announcements, audio ducking, and pre‑show lighting fade. |
| **Hardware** | XR18 mixer IP, channels, and show/idle music levels. |

**Kiosk** (`kiosk.php`, opened from the header or bookmarked) — a full‑screen, touch‑first page for helpers:

- Giant **SHOW RUNNING / IDLE / DISABLED** state with the current sequence.
- One‑tap **Start** (next scheduled show) and **hold‑to‑stop**.
- Temporary volume, and **Disable system** for an hour / tonight.
- **Live 3D preview** of the running show (if the [3D Viewer](#optional-3d-viewer) plugin is installed).
- A decorative light string and the whole page tint that **mirror a real string from the show** in real time.

---

## Features

- **Calendar scheduler** — schedule shows by date, not just day‑of‑week. Repeating rules (e.g. every Fri/Sat, 7–10 pm, every 30 min) and one‑off entries.
- **Blackouts = quiet hours** — block a whole day, or a time range. A blackout silences **audio** (shows and background music) but **leaves the lighting effect running** — for venues with quiet hours.
- **Background music & effects** — during their own daily windows, loop a background playlist (audio) and overlay a background lighting effect. Both stand down while a show runs, on a system‑disable, or a manual stop.
- **Pre‑show announcements** — play audio N minutes before each show; music ducks automatically while it plays. Files can come from the plugin's folder or anywhere in FPP's media.
- **Daytime announcements** — random clips on an interval during configurable hours, suppressed near show times.
- **Pre‑show brightness fade** — brightness fades to a pre‑show level over a configurable time (starting either the fade time before the show, or when a chosen pre‑show audio begins), then snaps to normal as the show starts (after the background overlay is cleared).
- **Bidirectional XR18 volume sync** — FPP master volume ↔ XR18 music faders over OSC; a separate announcement channel is held at its own level. Show/idle fader levels are applied around each show.
- **Kiosk** — the wall‑tablet control surface described above.
- **Robust by design** — a single‑instance lock prevents duplicate schedulers; each thread catches its own exceptions; a watchdog clears stale coordination flags; feeds auto‑reconnect. The plugin also re‑enables FPP's native scheduler when its schedule is empty, clearing the "FPP Scheduler is disabled" warning.

---

## Requirements

| Requirement | Notes |
|---|---|
| Falcon Pi Player **9.4+** | The plugin is served by FPP; the daemons run under Python 3. |
| `ffmpeg` **or** `mpg123` | Announcement audio playback (`sudo apt install -y ffmpeg`). |
| [fpp‑brightness](https://github.com/FalconChristmas/fpp-brightness) plugin | Pre‑show lighting fade (optional — the fade no‑ops if absent). |
| Behringer XR18 on the LAN | Optional — volume sync/ducking no‑op without it. OSC travels over UDP; audio over USB. |

### Optional: 3D Viewer

The kiosk's live 3D preview and show‑synced garland read data from the companion **fpp‑plugin‑3DViewer** (served at `/3dviewer/`). Both features auto‑hide if it isn't installed — everything else works without it.

---

## Installation

Install via the FPP **Plugin Manager** (repo `burnt-past/fpp-ShowManager`), then:

```bash
sudo apt install -y ffmpeg          # announcement playback (if not already present)
```

The plugin's daemons (`xr18_bridge.py`, `show_scheduler.py`) start automatically on boot via `scripts/postStart.sh`, the hook FPP runs after fppd starts. After installing or updating, restart them once — **Plugin Setup → Restart Daemons**, the **Restart Scheduler** button on the Status tab, or a reboot.

Announcement audio can be uploaded from the Announcements tab, or dropped into:

```
/home/fpp/media/plugins/fpp-ShowManager/announcements/         ← pre‑show
/home/fpp/media/plugins/fpp-ShowManager/announcements/daytime/ ← daytime
```

Pre‑show rows can also point at any audio already in FPP's `music/` or `upload/` folders.

See **[docs/setup.md](docs/setup.md)** for the full guide.

---

## How it works

Two daemons run alongside FPP:

- **`xr18_bridge.py`** — keeps FPP master volume and the XR18 music faders in sync over OSC (UDP 10024), holds the announcement channel at its own level, and shares the current fader level with the scheduler.
- **`show_scheduler.py`** — four loops: the **schedule** loop (shows, pre‑show announcements, brightness fade), the **daytime** announcer, the **background** loop (music/effect windows), and a **watchdog**. It drives FPP through its HTTP API and the brightness plugin.

They coordinate through small files in `/tmp` (a pause‑sync flag during announcements, the current fader level, a manual‑stop flag, and the background status the UI reads).

**Around a show:** the brightness fades down through the pre‑show window → at show time the background overlay effect is stopped and brightness snaps to normal → the show playlist starts → the scheduler waits for that playlist to end (tracking it by name) → dim/idle levels restore and background resumes per its schedule.

---

## Configuration files

Stored in `/home/fpp/media/config/`:

| File | Contents |
|---|---|
| `ShowManagerHardware.config` | Mixer IP, channels, show/idle/announce levels |
| `ShowManagerSchedule.config` | Calendar entries (shows, rules, blackouts) |
| `ShowManagerBackground.config` | Background music + effect windows |
| `ShowManagerAnnouncements.config` | Announcement, ducking, and brightness settings |
| `ShowManagerOverrides.config` | Kiosk "disable system until…" state |
| `ShowManagerRotation.config` | Auto‑managed rotation index for playlist groups |
| `ShowManager.config` | Legacy hardware config (read as a fallback) |

## Log files

| File | Contains |
|---|---|
| `/home/fpp/media/logs/showmanager.log` | Show triggers, announcements, background, brightness, errors |
| `/home/fpp/media/logs/xr18_bridge.log` | Volume sync and XR18 OSC activity |

The Status tab shows a live tail of `showmanager.log` with pause/copy/clear.

---

## Docs

- [Setup](docs/setup.md)
- [Scheduling](docs/scheduling.md)
- [Announcements](docs/announcements.md)
- [Configuration](docs/configuration.md)
- [Troubleshooting](docs/troubleshooting.md)

---

## Author

Alexander Woolum — [adeptevents.com](https://adeptevents.com)

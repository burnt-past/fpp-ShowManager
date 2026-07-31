# FPP Show Manager

A [Falcon Pi Player](https://github.com/FalconChristmas/fpp) plugin for running a scheduled outdoor light‑and‑music show. It adds a calendar scheduler, background music and lighting automation, pre‑show announcements, bidirectional volume sync with a **Behringer XR18** mixer, and a full‑screen **kiosk** for wall‑mounted tablets — including a live 3D preview and a page that changes color with the show.

Targets **FPP 9.4+**.

---

## Two interfaces

**Show Manager** (in the FPP menu) — the operator UI, styled to blend with FPP. Six tabs:

| Tab | What it does |
|---|---|
| **Status** | Live now‑playing (song **title/artist from the file's tags**), FPP/mixer meters, **live volume sliders** (music + announce, sent straight to the mixer over OSC), schedule‑sanity warnings, a manual trigger (**Run Show** through the full pipeline, or a raw start/stop), **tonight's timeline** with a live countdown to the next event, system health, and the scheduler log. |
| **Schedule** | Month/Week/Day calendar. One‑off shows, repeating rules, and blackouts (whole‑day or a time range). Background music/effect windows are shown on the calendar too. |
| **Background** | Two independent daily schedules — background **music** (a looping playlist, with its own volume level) and a background **effect** (a `.eseq` or `.fseq` overlay), each with its own window. |
| **Announcements** | Pre‑show announcements, daytime announcements, audio ducking, and the pre/post‑show lighting fade. |
| **Hardware** | XR18 mixer IP, channels, and show/idle music levels. |
| **System** | One‑click **diagnostics** (rig health check + light‑flash test), **backup / restore** of all settings (download a file, or upload to **Dropbox** manually or nightly). |

**Kiosk** (`kiosk.php`, opened from the header or bookmarked) — a full‑screen, touch‑first page for helpers:

- Giant **SHOW RUNNING / BG MUSIC PLAYING / IDLE / DISABLED** state with the current song — **title & artist read from the file's tags** (via ffprobe), falling back to the filename if untagged. (Looping background music reads as *BG Music Playing*, not a show — and you can still start a show over it.)
- One‑tap **Start** (next scheduled show) and **hold‑to‑stop**.
- Temporary volume, and **Disable system** for an hour / tonight.
- **Live 3D preview** of the running show (if the [3D Viewer](#optional-3d-viewer) plugin is installed).
- A decorative light string and the whole page tint that **mirror a real string from the show** in real time.

---

## Features

- **Calendar scheduler** — schedule shows by date, not just day‑of‑week. Repeating rules (e.g. every Fri/Sat, 7–10 pm, every 30 min) and one‑off entries.
- **Blackouts = quiet hours** — block a whole day, or a time range. A blackout silences **audio** (shows and background music) but **leaves the lighting effect running** — for venues with quiet hours.
- **Background music & effects** — during their own daily windows, loop a background playlist (audio) and overlay a background lighting effect. Both stand down while a show runs or on a system‑disable. A manual **Stop** only ends the current playback — background resumes per its schedule.
- **Pre‑show announcements** — play audio N minutes before each show; music ducks automatically while it plays. Files can come from the plugin's folder or anywhere in FPP's media.
- **Daytime announcements** — random clips on an interval during configurable hours, suppressed near show times.
- **Pre/post‑show brightness fade** — before a show, brightness fades to a pre‑show level over a configurable time (starting either the fade time before the show, or when a chosen pre‑show audio begins), then snaps to normal as the show starts (after the background overlay is cleared). When the show ends, brightness snaps to 0 and fades back up to normal over a configurable post‑show fade.
- **Plugin‑owned mixer levels** — the plugin drives the X‑Air music faders over OSC (show / idle / background / duck levels, plus live sliders on the Status tab), and **syncs both ways**: move a fader on the mixer and the Status page follows. A separate announcement channel is held at its own level. FPP's own volume is not involved.
- **Run Show on demand** — fire any playlist through the full show pipeline (dim, fader levels, effect kill, end detection, post‑show fade) straight from the Status tab, without editing the schedule.
- **Diagnostics & backup** — the System tab health‑checks the whole rig before an event and exports/restores every setting as one file, or backs up to **Dropbox** on demand or automatically each night.
- **Kiosk** — the wall‑tablet control surface described above.
- **Robust by design** — a single‑instance lock prevents duplicate schedulers; each thread catches its own exceptions; a watchdog clears stale coordination flags; logs rotate automatically; feeds auto‑reconnect. The plugin also re‑enables FPP's native scheduler when its schedule is empty, clearing the "FPP Scheduler is disabled" warning.

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

The plugin's daemons (`xr18_bridge.py`, `show_scheduler.py`) start automatically on boot via `scripts/postStart.sh`, the hook FPP runs after fppd starts. A plugin **update** runs `fpp_install.sh`, which stops the old daemons before starting the freshly-pulled code — so updates don't leave duplicates. (Both daemons also hold single-instance locks as a backstop.) You can always restart them by hand — **Plugin Setup → Restart Daemons**, the **Restart Scheduler** button, or `scripts/restart_daemons.sh`.

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

- **`xr18_bridge.py`** — holds the announcement channel at its own level and listens to the mixer over OSC (UDP 10024), mirroring manual music‑fader moves into the shared fader state so the UI and ducking track the real board. (The scheduler and Status sliders drive the fader outward; FPP volume is not used.)
- **`show_scheduler.py`** — five loops: the **schedule** loop (shows, pre‑show announcements, brightness fade), the **daytime** announcer, the **background** loop (music/effect windows), a **watchdog**, and a **run‑now** loop (the Status tab's Run Show). It drives FPP through its HTTP API and the brightness plugin.

They coordinate through small files in `/tmp` (a pause‑sync flag during announcements, the current fader level, a manual‑stop flag, and the background status the UI reads).

**Around a show:** the brightness fades down through the pre‑show window → at show time the background overlay effect is stopped and brightness snaps to normal → the show playlist starts → the scheduler waits for that playlist to end (tracking it by name) → background resumes per its schedule, idle fader level restores, and the lights snap to 0 and fade back up to normal over the post‑show fade.

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

The Status tab shows a live tail of `showmanager.log` with pause/copy/clear. Both logs rotate automatically (≈2 MB each, 3 old copies kept) — no cron needed.

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

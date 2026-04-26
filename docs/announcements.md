# Announcement System

The plugin has two independent announcement modes: **pre-show** (timed to show starts) and **daytime general** (random interval throughout the day).

---

## How ducking works

When any announcement plays, the following sequence happens automatically:

```
Normal state:
  XR18 ch1+ch2 faders at, say, 0.75 (75%)
  Music playing at full ALSA level → heard at 75% fader level

Announcement triggered:
  1. Bridge pauses fader sync (writes PAUSE_SYNC_FLAG)
  2. Scheduler reads current fader level from FADER_STATE_FILE
  3. Faders fade from 0.75 → 0.25 over duck_fade_secs (e.g. 2s)
  4. ffmpeg plays the MP3 at +6 dB gain through ALSA default device
     → Music at 25% fader, announcement at full level + 6 dB boost
     → Announcement is clearly audible over the ducked music
  5. Faders fade from 0.25 → 0.75 over duck_fade_secs
  6. PAUSE_SYNC_FLAG removed → bridge resumes normal sync

Total interruption: duck_fade_secs × 2 + announcement_duration
```

The music never stops — it stays playing at the ducked level the entire time. Only the fader level changes.

---

## Announcement settings page

```
┌─────────────────────────────────────────────────────────────────┐
│ Announcement Settings                                            │
│─────────────────────────────────────────────────────────────────│
│ DUCKING                                                          │
│  Music duck level during announcement  [ 0.25 ]                 │
│  Fade duration                         [  2.0 ] seconds         │
│  Announcement gain boost               [    6 ] dB              │
│  Max announcement duration             [  300 ] sec             │
│                                                                  │
│ LIGHTING (fpp-brightness)                                        │
│  Pre-show brightness   [ 20  ]  (0-200, applied before show)    │
│  Normal brightness     [ 100 ]  (restored after show)           │
│                                                                  │
│ BACKGROUND MUSIC                                                 │
│  Background playlist   [ BackgroundMusic ▾ ]                    │
│                                                                  │
│ PRE-SHOW ANNOUNCEMENTS                                           │
│  Minutes before  │ Audio file                                    │
│       15         │ 15min.mp3             [Remove]               │
│       10         │ 10min.mp3             [Remove]               │
│        5         │  5min.mp3             [Remove]               │
│                                          [+ Add Announcement]   │
│                                                                  │
│ DAYTIME GENERAL ANNOUNCEMENTS                                    │
│  Enable  [✓]                                                     │
│  Window  10:00  to  18:00                                        │
│  Interval  [ 20 ] minutes                                        │
│                                                                  │
│                                         [Save Settings]         │
└─────────────────────────────────────────────────────────────────┘
```

---

## Pre-show announcements

### Audio files

Place MP3 files in:
```
/home/fpp/media/plugins/ShowManager/announcements/
```

Copy files via SFTP (FileZilla, Cyberduck) or `scp`:

```bash
scp 5min.mp3 fpp@your-pi-ip:/home/fpp/media/plugins/ShowManager/announcements/
```

The **Announcements** page shows a dropdown of detected MP3s in that folder to prevent typos.

### Adding an announcement row

1. Click **+ Add Announcement**
2. Set **Minutes before show** (e.g. `5`, `10`, `15`)
3. Type the filename or pick from the autocomplete list (e.g. `5min.mp3`)
4. Click **Save Settings**

You can have as many rows as you want. There is no requirement to have all three — if you only want a 5-minute warning, add only that row.

### Timing precision

The scheduler runs on a 30-second tick. A pre-show announcement fires when the scheduler wakes up and finds the window is within ±30 seconds of the configured offset. In practice announcements fire within 30 seconds of their target time.

### What happens if a show is running when an announcement is due

It's skipped silently and logged. The duplicate-prevention set means it won't retry later in the day — each announcement fires at most once per show slot per day.

---

## Daytime general announcements

### Audio files

Place MP3s in the daytime subfolder:
```
/home/fpp/media/plugins/ShowManager/announcements/daytime/
```

The scheduler picks a **random file** from this folder each time. If the folder is empty it falls back to the main announcements folder.

To control playback order, prefix filenames with numbers: `01_ad.mp3`, `02_ad.mp3`, etc. — the random selection will still be random, but at least you can identify files easily in logs.

### Suppression rules

A daytime announcement is **skipped** if any of the following are true:

| Condition | Why |
|---|---|
| Current time is outside the configured window | Respects quiet hours |
| Less than 20 minutes until a scheduled show | Prevents an announcement from overlapping into a pre-show period |
| An FPP sequence (`.fseq`) is actively running | Show safety — never interrupts a live show |
| Another announcement is already playing | The loop waits 60 seconds between checks |

### Interval behaviour

`interval_mins` is the **minimum** time between announcements, not a fixed schedule. If a show runs for 15 minutes and then the background music resumes, the interval timer picks up from when the last announcement actually finished. You won't get a burst of announcements after a show ends.

---

## Gain boost and duck level — tuning guide

These two settings work together. The goal is: announcement clearly audible, music noticeable but not competing.

**Starting point (defaults):**
- `duck_level`: `0.25` — music drops to 25% of its current fader level
- `gain_db`: `6` — announcement is +6 dB (≈ 2×) louder than the un-ducked music level

**If the announcement is too quiet:**
- Increase `gain_db` (try `9` or `12`)
- Decrease `duck_level` (try `0.15`)

**If the music is still too loud underneath:**
- Decrease `duck_level` (try `0.10`)

**If the fade feels abrupt:**
- Increase `duck_fade_secs` (try `3.0` or `4.0`)

**Reference table:**

| gain_db | Perceived loudness vs. music at duck_level 0.25 |
|---|---|
| 0 | Same as music — not recommended |
| 6 | 2× louder — good starting point |
| 12 | 4× louder — clear in noisy environments |
| 18 | 8× louder — very prominent |

---

## Background playlist

After every show ends, the scheduler starts the configured **background playlist** in looping mode (`repeat=1`). This happens automatically — you don't need to do anything.

If no background playlist is configured, FPP will return to idle after the show and stay there. Background music will not resume automatically.

The background playlist should be a standard FPP audio-only playlist. It does not need a sequence file — a list of MP3s in FPP's playlist editor is sufficient.

---

## Announcement audio format recommendations

| Property | Recommendation |
|---|---|
| Format | MP3 (widely supported by both ffmpeg and mpg123) |
| Sample rate | 44.1 kHz or 48 kHz |
| Bit rate | 128–320 kbps |
| Channels | Stereo or mono — both work |
| Loudness | Normalize to –14 LUFS for consistent levels |
| Length | Keep pre-show announcements under 60 seconds |

Normalizing your announcement files means the `gain_db` setting has a consistent reference point. Use `ffmpeg-normalize` or Audacity's normalization feature before putting files on the FPP host.

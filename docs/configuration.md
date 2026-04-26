# Configuration Reference

All settings are stored as JSON files in `/home/fpp/media/config/`. They are written by the plugin's web pages and read by the daemons. You can edit them directly if needed — the daemons pick up changes within 30–60 seconds without a restart.

---

## ShowManager.config — Hardware

Managed by the **Hardware** page.

```json
{
  "xr18_ip":       "192.168.1.50",
  "music_ch1":     "01",
  "music_ch2":     "02",
  "announce_ch":   "03",
  "announce_vol":  "0.75",
  "duck_level":    0.25,
  "duck_fade_secs": 2.0
}
```

| Key | Type | Default | Description |
|---|---|---|---|
| `xr18_ip` | string | `192.168.0.1` | IP address of the XR18 on your network |
| `music_ch1` | string | `01` | XR18 input channel number for music left (01–18) |
| `music_ch2` | string | `02` | XR18 input channel number for music right (01–18) |
| `announce_ch` | string | `03` | XR18 channel maintained at a fixed fader level for a separate announcement source |
| `announce_vol` | string/float | `0.75` | Fader level (0.0–1.0) held on the announcement channel |
| `duck_level` | float | `0.25` | Fader level music channels drop to during an announcement |
| `duck_fade_secs` | float | `2.0` | Duration of the fade down and fade up in seconds |

---

## ShowManagerShows.config — Show Definitions

Managed by the **Shows** page.

```json
{
  "shows": [
    {
      "id":                   "show_a",
      "name":                 "Show A",
      "playlist":             "ShowPlaylistA",
      "transition_playlist":  "PreShowFadeA",
      "transition_secs":      120,
      "duration_mins":        15
    },
    {
      "id":                   "show_b",
      "name":                 "Show B",
      "playlist":             "ShowPlaylistB",
      "transition_playlist":  "",
      "transition_secs":      0,
      "duration_mins":        12
    }
  ]
}
```

| Key | Type | Description |
|---|---|---|
| `id` | string | Unique identifier, no spaces (used in schedule entries) |
| `name` | string | Human-readable label shown in the calendar |
| `playlist` | string | Name of the FPP playlist to trigger at show time |
| `transition_playlist` | string | FPP playlist triggered before the show for lighting transitions (optional — leave blank to skip) |
| `transition_secs` | int | How many seconds to run the transition playlist before starting the main show |
| `duration_mins` | int | Approximate show duration — used as a ×3 safety timeout. Set to your actual show length; the scheduler polls FPP for the real end. |

---

## ShowManagerSchedule.config — Calendar

Managed by the **Schedule** page. You should not need to edit this manually.

```json
{
  "entries": [
    {
      "id":      "e_abc123",
      "date":    "2024-12-14",
      "type":    "show",
      "time":    "19:30",
      "show_id": "show_a"
    },
    {
      "id":           "e_def456",
      "date":         "2024-12-14",
      "type":         "show",
      "time":         "21:00",
      "rotation_ids": ["show_a", "show_b"]
    },
    {
      "id":     "e_ghi789",
      "date":   "2024-12-20",
      "type":   "blackout",
      "reason": "Private event"
    }
  ]
}
```

### Entry types

**`show` with specific playlist:**
```json
{ "type": "show", "date": "2024-12-14", "time": "19:30", "show_id": "show_a" }
```

**`show` with rotation:**
```json
{ "type": "show", "date": "2024-12-14", "time": "21:00", "rotation_ids": ["show_a", "show_b"] }
```
Rotation automatically alternates between shows in order each time that slot fires. State is tracked in `ShowManagerRotation.config`.

**`blackout`:**
```json
{ "type": "blackout", "date": "2024-12-20", "reason": "Private event" }
```
All shows and pre-show announcements for this date are suppressed.

---

## ShowManagerAnnouncements.config — Announcements

Managed by the **Announcements** page.

```json
{
  "folder":              "/home/fpp/media/plugins/ShowManager/announcements",
  "gain_db":             6.0,
  "max_duration_secs":   300,
  "background_playlist": "BackgroundMusic",
  "pre_show_brightness": 20,
  "normal_brightness":   100,
  "pre_show": [
    { "mins_before": 15, "file": "15min.mp3" },
    { "mins_before": 10, "file": "10min.mp3" },
    { "mins_before": 5,  "file": "5min.mp3"  }
  ],
  "daytime": {
    "enabled":       true,
    "start":         "10:00",
    "end":           "18:00",
    "interval_mins": 20,
    "folder":        "/home/fpp/media/plugins/ShowManager/announcements/daytime"
  }
}
```

| Key | Type | Default | Description |
|---|---|---|---|
| `folder` | string | — | Base folder for pre-show announcement MP3s |
| `gain_db` | float | `6.0` | Software volume boost applied to announcement audio. 6 dB ≈ 2× louder; 12 dB ≈ 4× louder. |
| `max_duration_secs` | int | `300` | Safety timeout — playback process is killed if it runs longer than this |
| `background_playlist` | string | — | FPP playlist started (looping) after every show ends |
| `pre_show_brightness` | int | `20` | fpp-brightness value set when a show is about to start (0–200, 100 = normal) |
| `normal_brightness` | int | `100` | fpp-brightness value restored after a show ends |
| `pre_show` | array | `[]` | List of `{mins_before, file}` announcements. Firing is within a ±30-second window. |
| `daytime.enabled` | bool | `false` | Whether daytime general announcements are active |
| `daytime.start` / `.end` | string | `10:00` / `18:00` | Time window for daytime announcements (HH:MM, 24-hour) |
| `daytime.interval_mins` | int | `20` | Minimum minutes between daytime announcements |
| `daytime.folder` | string | — | Folder for daytime MP3s (separate from pre-show) |

---

## ShowManagerRotation.config — Rotation State

**Auto-managed — do not edit manually.**

Tracks which show in each rotation group was last played so the next one is always different.

```json
{
  "rotations": {
    "show_a,show_b": { "next_index": 1 }
  }
}
```

The key is the comma-joined list of `rotation_ids` in the schedule entry. The `next_index` advances by 1 each time the slot fires, wrapping around.

To reset a rotation (start back from the first show), delete the matching key from this file, or delete the file entirely.

---

## OSC port reference

| Direction | Protocol | Port | Purpose |
|---|---|---|---|
| FPP host → XR18 | UDP | 10024 | Send fader commands, `/xremote` heartbeat |
| XR18 → FPP host | UDP | 10023 | Receive fader update notifications |

Ensure no firewall blocks these ports on the FPP host or your router/switch.

---

## fpp-brightness API

The plugin calls the fpp-brightness REST endpoint directly:

```
GET http://localhost/api/plugin-apis/Brightness/{value}
```

- **0** — completely off
- **100** — normal/unity brightness
- **200** — double brightness

The `pre_show_brightness` and `normal_brightness` settings map directly to this value.

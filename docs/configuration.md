# Configuration Reference

All settings are JSON files in `/home/fpp/media/config/`. The plugin's tabs write them and the daemons read them — the scheduler picks up changes within about a minute without a restart. You can edit them directly if needed.

The plugin also writes a few coordination files under `/tmp` (see [Runtime files](#runtime-files)); those are not configuration and should not be edited.

---

## ShowManagerHardware.config — Hardware

Managed by the **Hardware** tab. Also seeds the XR18 bridge.

```json
{
  "mixer_ip":      "192.168.1.50",
  "fader_channel": 1,
  "show_level":    0.75,
  "idle_level":    0.0,
  "announce_ch":     3,
  "announce_vol":    0.75,
  "announce_device": "default"
}
```

| Key | Type | Default | Description |
|---|---|---|---|
| `mixer_ip` | string | `192.168.0.1` | IP address of the XR18 |
| `fader_channel` | int | `1` | XR18 music channel synced with FPP volume |
| `show_level` | float | `0.75` | Music fader level applied when a show starts |
| `idle_level` | float | `0.0` | Music fader level applied after a show ends |
| `announce_ch` | int | `3` | XR18 channel held at a fixed level for a separate announcement source |
| `announce_vol` | float | `0.75` | Fader level (0–1) held on the announcement channel |
| `announce_device` | string | `default` | ALSA device announcements play to (mono). Point at a separate device (a USB adapter into a mixer line input) to give announcements their own channel — see [announcements.md](announcements.md#announcements-on-their-own-mixer-channel) |

> Ducking settings (`duck_level`, `duck_fade_secs`) live in the Announcements config, below.

Older installs may have a legacy `ShowManager.config` with `xr18_ip`/`music_ch1`/`music_ch2` — it is read as a fallback only.

---

## ShowManagerSchedule.config — Calendar

Managed by the **Schedule** tab. One `entries` array (one-off shows and blackouts) plus a `rules` array (repeating shows).

```json
{
  "entries": [
    { "id": "e_abc", "date": "2026-12-14", "type": "show", "time": "19:30",
      "playlist": "Main Street Magic" },

    { "id": "e_def", "date": "2026-12-14", "type": "show", "time": "21:00",
      "playlists": ["Show A", "Show B"] },

    { "id": "e_ghi", "date": "2026-12-24", "type": "blackout" },

    { "id": "e_jkl", "date": "2026-12-25", "type": "blackout",
      "start_time": "22:00", "end_time": "23:00" }
  ],
  "rules": [
    { "id": "r_1", "playlist": "Main Street Magic",
      "days": [5, 6], "window_start": "19:00", "window_end": "22:00",
      "interval_mins": 30, "start_date": "2026-11-28", "end_date": "2026-12-31" }
  ]
}
```

### Show entries

| Key | Description |
|---|---|
| `type` | `"show"` |
| `date` | `YYYY-MM-DD` |
| `time` | `HH:MM` (24-hour) |
| `playlist` | FPP playlist to start; **or** |
| `playlists` | list of playlists that rotate — a different one each time the slot fires (state in `ShowManagerRotation.config`) |

### Blackout entries

| Key | Description |
|---|---|
| `type` | `"blackout"` |
| `date` | `YYYY-MM-DD` |
| `start_time` / `end_time` | optional `HH:MM` range. Omit both for a whole-day blackout. |

A blackout is the venue's **quiet hours**: it suppresses shows and background **music** (audio), but background **lighting effects keep running**.

### Repeating rules

| Key | Description |
|---|---|
| `playlist` / `playlists` | same as show entries |
| `days` | day-of-week list, `0`=Sun … `6`=Sat |
| `window_start` | first show time `HH:MM` |
| `window_end` | optional — with `interval_mins`, generates shows across the window |
| `interval_mins` | optional — spacing between generated shows |
| `start_date` / `end_date` | active date range |

Rules are expanded into virtual show entries per day; you don't edit them by hand.

---

## ShowManagerBackground.config — Background music & effects

Managed by the **Background** tab. Two independent daily windows.

```json
{
  "music":  { "enabled": true,  "playlist": "Ambient Mix",
              "start": "16:00", "end": "23:00", "level": 0.4 },
  "effect": { "enabled": true,  "effect": "Standby-V2", "type": "fseq",
              "start": "17:00", "end": "05:00" }
}
```

| Key | Description |
|---|---|
| `music.enabled` | loop the background playlist during its window |
| `music.playlist` | FPP playlist (audio) to loop when idle |
| `music.start` / `.end` | daily window `HH:MM`; equal start & end = all day |
| `music.level` | float (0–1) or omitted | music fader level while background music plays; blank/omitted leaves the fader at the idle level |
| `effect.enabled` | run the background overlay during its window |
| `effect.effect` | name of the `.eseq` effect or `.fseq` sequence |
| `effect.type` | `"eseq"` or `"fseq"` (selects the FPP command used) |
| `effect.start` / `.end` | daily window `HH:MM`; windows may cross midnight |

Background music plays only when FPP is idle (never over a show) and is silenced by blackouts. The effect is an overlay: suppressed only while a show runs, but it keeps running through blackouts. Both stand down on a system disable. A manual **Stop** only ends the current playback — background then resumes per its schedule.

For backward compatibility, an old `background_playlist` in the Announcements config is treated as an always-on music window until this file is configured.

---

## ShowManagerAnnouncements.config — Announcements, ducking & lighting

Managed by the **Announcements** tab.

```json
{
  "duck_level": 0.25,
  "duck_fade_secs": 2.0,
  "gain_db": 6.0,
  "max_duration_secs": 300,
  "pre_show_brightness": 20,
  "normal_brightness": 100,
  "pre_show_fade_secs": 30,
  "fade_anchor_mins": "1",
  "pre_show": [
    { "mins_before": 5, "file": "preshow_5min.mp3" },
    { "mins_before": 1, "file": "countdown.mp3" }
  ],
  "daytime": {
    "enabled": true, "start": "10:00", "end": "18:00", "interval_mins": 20
  }
}
```

| Key | Type | Default | Description |
|---|---|---|---|
| `duck_level` | float | `0.25` | Fader level music drops to during an announcement |
| `duck_fade_secs` | float | `2.0` | Duration of the duck fade down/up |
| `gain_db` | float | `6.0` | Software volume boost on announcement audio (6 dB ≈ 2×) |
| `max_duration_secs` | int | `300` | Playback is killed if it runs longer than this |
| `pre_show_brightness` | int | `20` | Brightness the pre-show fade dims to (0–200) |
| `normal_brightness` | int | `100` | Brightness snapped to at show start |
| `pre_show_fade_secs` | int | `30` | Duration of the pre-show brightness fade |
| `fade_anchor_mins` | string | `""` | `""` = fade starts the fade time before the show; a number = fade starts when the pre-show announcement at that `mins_before` begins |
| `pre_show` | array | `[]` | `{mins_before, file}` rows; `file` may be a bare name or an absolute path |
| `daytime.enabled` | bool | `false` | Random daytime announcements on an interval |
| `daytime.start` / `.end` | string | `10:00`/`18:00` | Daytime window (HH:MM) |
| `daytime.interval_mins` | int | `20` | Minimum minutes between daytime announcements |

**Pre-show brightness fade:** brightness eases from `normal_brightness` down to `pre_show_brightness` over `pre_show_fade_secs`, holds dim until show time, then — after the background overlay effect is stopped — snaps back to `normal_brightness` as the show begins. The fade begins either `pre_show_fade_secs` before the show, or (if `fade_anchor_mins` is set) when that pre-show announcement plays. Requires the [fpp-brightness](https://github.com/FalconChristmas/fpp-brightness) plugin; no-ops without it.

---

## ShowManagerOverrides.config — Disable override

Written by the **kiosk** (and the "Disable system" controls). Empty when the system is enabled.

```json
{ "disabled_until": "2026-12-25T04:00:00" }
```

While `disabled_until` is in the future, the scheduler skips shows, background music, and background effects. It clears itself when the time passes or when re-enabled.

---

## ShowManagerRotation.config — Rotation state

**Auto-managed — do not edit.** Tracks which entry in a `playlists` rotation fires next.

```json
{ "rotations": { "Show A,Show B": { "next_index": 1 } } }
```

The key is the comma-joined playlist list. Delete a key (or the file) to reset a rotation to the first playlist.

---

## ShowManagerDropbox.config — Dropbox backup

Managed by the **System** tab (Dropbox Backup). Holds the credentials for uploading backups to Dropbox. Written mode `0600`, and **never included in a backup bundle** (so the secret/token don't leave the Pi in a downloadable or uploaded backup).

```json
{
  "app_key": "abcd1234efgh",
  "app_secret": "…",
  "refresh_token": "…",
  "folder": "/ShowManager",
  "auto": true,
  "last_backup": "2026-07-17T04:00:03-07:00"
}
```

| Key | Description |
|---|---|
| `app_key` / `app_secret` | Your Dropbox app's key and secret (scoped app, `files.content.write`) |
| `refresh_token` | Long-lived token obtained via the one-time authorize step; exchanged for a short-lived access token per upload |
| `folder` | Dropbox path backups are written to (relative to an app-folder app) |
| `auto` | When true, the scheduler uploads a backup once daily after 04:00 |
| `last_backup` | Timestamp of the last successful upload (auto-set) |

**Setup:** create a Dropbox app at [dropbox.com/developers](https://www.dropbox.com/developers/apps), paste its App key & secret on the System tab, click **Authorize** (approve, copy the code Dropbox shows), paste the code, **Connect**. Requires outbound HTTPS from the Pi.

---

## ShowManagerPublish.config — Website link

Managed by the **System** tab (Website Link). Configures publishing the public schedule feed to an external website. Written mode `0600`, and **never included in a backup bundle** (so the upload auth token doesn't leave the box). This file ships empty — no hostnames or credentials are baked into the plugin; everything here is entered by the operator.

```json
{
  "feed_key": "9f2c…",
  "paused": false,
  "status_note": "",
  "allow_origin": "",
  "events": [
    { "name": "Opening Night", "when": "2026-11-27T18:00", "label": "", "desc": "The plaza lights up for the first time." }
  ],

  "enabled": false,
  "url": "https://your-host.example/upload/schedule.json",
  "method": "PUT",
  "auth_header": "Authorization",
  "auth_value": "Bearer …",
  "interval_mins": 5,
  "last_publish": "2026-11-20T17:05:00-08:00",
  "last_status": "ok"
}
```

**Primary path — the site pulls the feed:**

| Key | Description |
|---|---|
| `feed_key` | Unguessable token required on the feed URL (`&key=…`). The site's proxy sends it on a server-to-server fetch, so it never reaches a visitor's browser. Leave empty to serve the feed unauthenticated (not recommended). URL-safe chars only |
| `paused` / `status_note` | Flip the site to its paused banner and show a note (e.g. "Shows paused for high winds"). The site is also paused automatically while the system is disabled |
| `allow_origin` | `Access-Control-Allow-Origin` on the feed. Empty by default (no header) — CORS isn't needed for a server-to-server pull; set it only if a browser fetches the feed directly |
| `events` | Optional special-event cards. Each has a `name`, a `desc`, and either a `when` (datetime → ISO `iso` + human `date`) or a free-text `label` for open-ended entries like "All season". Some sites render these; others ignore them |

**Alternative path — the plugin pushes to a static host** (used only if your site serves its own copy instead of pulling):

| Key | Description |
|---|---|
| `enabled` | When true, the scheduler pushes the feed to `url` every `interval_mins` |
| `url` | Where to upload the feed on your static host |
| `method` | `PUT` or `POST` — how your host accepts the upload |
| `auth_header` / `auth_value` | Header sent with the upload; `auth_value` is write-only — the UI never reads it back |
| `interval_mins` | Auto-publish period, 1–1440 minutes |
| `last_publish` / `last_status` / `last_error` | Result of the last push (auto-set) |

The feed (the contract with the website) is served read-only at
`plugin.php?plugin=fpp-ShowManager&page=ajax.php&nopage=1&action=public_schedule[&key=…]`
with `Cache-Control: public, max-age=60`. See
[website-integration.md](website-integration.md) for the response shape and how
to expose only this path via a tunnel without putting the box on the internet.

---

## Runtime files (`/tmp`)

Not configuration — coordination state, safe to delete when the daemons are stopped.

| File | Purpose |
|---|---|
| `xr18_pause_sync` | Set while an announcement ducks; pauses the bridge's fader sync |
| `xr18_current_fader` | Current music fader level, shared bridge → scheduler |
| `showmanager_bg_status.json` | Live background music/effect status the Status tab reads |
| `showmanager_run_now` | A Run Show request the scheduler picks up (deleted once consumed) |
| `showmanager_cloud_backup_day` | Date of the last successful nightly Dropbox backup (de-dupes to once/day) |
| `showmanager_scheduler.lock` | Single-instance lock (flock) so only one scheduler runs |
| `showmanager_bridge.lock` | Single-instance lock (flock) so only one XR18 bridge runs |

---

## OSC & API reference

| Direction | Protocol | Port | Purpose |
|---|---|---|---|
| FPP → XR18 | UDP | 10024 | Fader commands, `/xremote` heartbeat |
| XR18 → FPP | UDP | 10023 | Fader update notifications |

FPP HTTP API endpoints the plugin uses:

- `GET /api/fppd/status` — playback state, current playlist/sequence, volume
- `GET /api/command/Start%20Playlist/{name}/{repeat}` — start a playlist
- `GET /api/command/Stop%20Now` — stop playback
- `GET /api/command/Effect%20Start/{name}/{ch}/{loop}/{bg}` — start an `.eseq` overlay
- `GET /api/command/FSEQ%20Effect%20Start/{name}/{loop}/{bg}` — overlay an `.fseq`
- `GET /api/command/Effect%20Stop/{name}` · `.../FSEQ%20Effect%20Stop/{name}` — stop an overlay
- `GET /api/plugin-apis/Brightness/{0-200}` — set global brightness (fpp-brightness)
- `GET /api/system/volume` · `PUT /api/system/volume` — read/set FPP volume

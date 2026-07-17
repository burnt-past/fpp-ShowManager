#!/usr/bin/env python3
"""
XR18 Show Scheduler for Falcon Pi Player

Manages show scheduling, pre-show announcements, daytime announcements,
and background music resume. Coordinates with xr18_bridge.py via two
flag/state files:
  /tmp/xr18_pause_sync        — created to pause bridge fader sync
  /tmp/xr18_current_fader     — bridge writes current fader level here

Config files (all in /home/fpp/media/config/):
  ShowManagerHardware.config         — mixer settings (IP, channels, duck/show/idle levels)
  ShowManagerShows.config            — legacy show definitions
  ShowManagerSchedule.config         — calendar entries (shows + blackouts)
  ShowManagerAnnouncements.config    — announcement settings
"""

import datetime
import fcntl
import glob
import json
import logging
import os
import random
import socket
import struct
import subprocess
import threading
import time
import urllib.parse
import urllib.request

# ---------------------------------------------------------------------------
# Paths
# ---------------------------------------------------------------------------

HARDWARE_CONFIG  = "/home/fpp/media/config/ShowManagerHardware.config"
SHOWS_CONFIG     = "/home/fpp/media/config/ShowManagerShows.config"
SCHEDULE_CONFIG  = "/home/fpp/media/config/ShowManagerSchedule.config"
ANNOUNCE_CONFIG  = "/home/fpp/media/config/ShowManagerAnnouncements.config"
ROTATION_STATE   = "/home/fpp/media/config/ShowManagerRotation.config"
OVERRIDES_CONFIG = "/home/fpp/media/config/ShowManagerOverrides.config"
BACKGROUND_CONFIG = "/home/fpp/media/config/ShowManagerBackground.config"
PAUSE_SYNC_FLAG  = "/tmp/xr18_pause_sync"
FADER_STATE_FILE = "/tmp/xr18_current_fader"
MANUAL_STOP_FLAG = "/tmp/showmanager_manual_stop"
BG_STATUS_FILE   = "/tmp/showmanager_bg_status.json"
LOG_PATH         = "/home/fpp/media/logs/showmanager.log"

XR18_PORT        = 10024
ANNOUNCE_FOLDER  = os.path.join(os.path.dirname(os.path.dirname(__file__)), "announcements")

logging.basicConfig(
    filename=LOG_PATH,
    level=logging.INFO,
    format="%(asctime)s %(levelname)s %(message)s",
)
log = logging.getLogger(__name__)


# ---------------------------------------------------------------------------
# Config helpers
# ---------------------------------------------------------------------------

def load_json(path, default=None):
    try:
        with open(path) as f:
            return json.load(f)
    except FileNotFoundError:
        return {} if default is None else default
    except Exception as e:
        log.error("Config load error %s: %s", path, e)
        return {} if default is None else default

def save_json(path, data):
    try:
        with open(path, "w") as f:
            json.dump(data, f, indent=2)
    except Exception as e:
        log.error("Config save error %s: %s", path, e)


# ---------------------------------------------------------------------------
# OSC helpers (self-contained; duplicated from bridge for independence)
# ---------------------------------------------------------------------------

def _pad4(b):
    return b + b"\x00" * ((4 - len(b) % 4) % 4)

def _build_osc(address, *args):
    msg = _pad4((address + "\x00").encode())
    tag = ","
    body = b""
    for a in args:
        if isinstance(a, float):
            tag += "f"
            body += struct.pack(">f", a)
        elif isinstance(a, int):
            tag += "i"
            body += struct.pack(">i", a)
    return msg + _pad4((tag + "\x00").encode()) + body

def _send_osc(ip, address, *args):
    try:
        data = _build_osc(address, *args)
        with socket.socket(socket.AF_INET, socket.SOCK_DGRAM) as s:
            s.sendto(data, (ip, XR18_PORT))
    except Exception as e:
        log.warning("OSC send failed %s: %s", address, e)

def _fade_faders(ip, ch1, ch2, from_lvl, to_lvl, duration_secs, steps=20):
    delay = duration_secs / steps
    for i in range(steps + 1):
        t = i / steps
        lvl = float(from_lvl + (to_lvl - from_lvl) * t)
        _send_osc(ip, f"/ch/{ch1.zfill(2)}/mix/fader", lvl)
        _send_osc(ip, f"/ch/{ch2.zfill(2)}/mix/fader", lvl)
        if i < steps:
            time.sleep(delay)


# ---------------------------------------------------------------------------
# FPP API helpers
# ---------------------------------------------------------------------------

def _fpp(path, method="GET", body=None, timeout=5):
    """Call the FPP API. Returns parsed JSON (dict/list), a string for
    non-JSON/empty 200 responses, or None on HTTP/network error."""
    url = f"http://localhost{path}"
    try:
        req = urllib.request.Request(url, method=method)
        if body:
            req.add_header("Content-Type", "application/json")
            req.data = json.dumps(body).encode()
        with urllib.request.urlopen(req, timeout=timeout) as r:
            data = r.read().strip()
            if not data:
                return ""
            try:
                return json.loads(data)
            except ValueError:
                return data.decode("utf-8", "replace")
    except urllib.error.HTTPError as e:
        log.warning("FPP API HTTP %d %s %s: %s", e.code, method, path, e.read()[:120])
        return None
    except Exception as e:
        log.warning("FPP API %s %s: %s", method, path, e)
        return None

def fpp_status():
    st = _fpp("/api/fppd/status")
    return st if isinstance(st, dict) else {}

def fpp_start_playlist(name, repeat=False):
    enc = urllib.parse.quote(name, safe='')
    repeat_str = 'true' if repeat else 'false'
    path = f"/api/command/Start%20Playlist/{enc}/{repeat_str}"
    result = _fpp(path)
    if result is None:
        log.error("Failed to start playlist '%s' — HTTP/network error", name)
        return False
    if isinstance(result, str) and result and "starting" not in result.lower():
        log.error("FPP rejected playlist start '%s': %s", name, result[:120])
        return False
    log.info("Started FPP playlist '%s' — response: %s", name, result)
    return True

def fpp_stop():
    _fpp("/api/command/Stop%20Now")
    log.info("FPP stop requested")

def fpp_put_setting(name, value):
    """FPP's settings API takes the value as a raw request body, not JSON."""
    try:
        req = urllib.request.Request(
            f"http://localhost/api/settings/{name}",
            data=str(value).encode(), method="PUT",
        )
        urllib.request.urlopen(req, timeout=5)
        return True
    except Exception as e:
        log.warning("Failed to set FPP setting %s: %s", name, e)
        return False

def ensure_fpp_scheduler_enabled():
    """Clear FPP's permanent 'FPP Scheduler is disabled' warning.

    The warning is added once at fppd startup when DisableScheduler=1 and is
    never removed at runtime. This plugin replaces the native scheduler, so as
    long as FPP's own schedule is EMPTY it is safe to leave the native
    scheduler enabled — it has nothing to run. Re-enable it here; the warning
    then disappears at the next fppd restart.
    """
    cur = _fpp("/api/settings/DisableScheduler")
    if isinstance(cur, dict):
        val = str(cur.get("value", cur.get("DisableScheduler", "0")))
    else:
        val = str(cur or "0").strip().strip('"')
    if val != "1":
        return

    sched = _fpp("/api/schedule")
    entries = sched if isinstance(sched, list) else []
    active = [e for e in entries if e.get("enabled", 1)]
    if active:
        log.warning(
            "FPP's native scheduler is disabled but its schedule still has %d "
            "enabled entr%s — leaving it disabled to avoid double-scheduling. "
            "Delete them in FPP's Scheduler page to clear the warning.",
            len(active), "y" if len(active) == 1 else "ies",
        )
        return

    if fpp_put_setting("DisableScheduler", "0"):
        log.info(
            "Re-enabled FPP's native scheduler (its schedule is empty, so it "
            "will do nothing). The 'FPP Scheduler is disabled' warning clears "
            "at the next FPPD restart."
        )

def fpp_get_volume():
    data = _fpp("/api/system/volume")
    return int(data.get("volume", 75)) if isinstance(data, dict) else 75

def is_show_running():
    """True only when an FSEQ sequence is actively playing (not background music)."""
    return bool(fpp_status().get("current_sequence", ""))

def fpp_current_playlist():
    """Name of the playlist FPP is currently playing, or '' when idle."""
    cur = fpp_status().get("current_playlist") or {}
    if not isinstance(cur, dict):
        return ""
    return str(cur.get("playlist") or cur.get("name") or "")


# ---------------------------------------------------------------------------
# FPP overlay effects (.eseq) — layer lighting on top of whatever is playing.
# Started/stopped via the command API (path-args form, proven on this box).
# ---------------------------------------------------------------------------

def fpp_start_effect(name, kind="eseq", start_channel=1, loop=True, background=True):
    """Start a looping background overlay. kind='eseq' uses an effect file;
    kind='fseq' overlays a full sequence (command names confirmed against
    FPP's own effects.php)."""
    enc = urllib.parse.quote(name, safe='')
    loop_s = 'true' if loop else 'false'
    bg_s   = 'true' if background else 'false'
    if kind == "fseq":
        path = f"/api/command/FSEQ%20Effect%20Start/{enc}/{loop_s}/{bg_s}"
    else:
        path = f"/api/command/Effect%20Start/{enc}/{start_channel}/{loop_s}/{bg_s}"
    result = _fpp(path)
    if result is None:
        log.error("Failed to start background %s '%s' — HTTP/network error", kind, name)
        return False
    log.info("Started background %s '%s' — response: %s", kind, name, result)
    return True

def fpp_stop_effect(name, kind="eseq"):
    enc = urllib.parse.quote(name, safe='')
    cmd = "FSEQ%20Effect%20Stop" if kind == "fseq" else "Effect%20Stop"
    _fpp(f"/api/command/{cmd}/{enc}")
    log.info("Stopped background %s '%s'", kind, name)


# ---------------------------------------------------------------------------
# Time-window + blackout helpers (shared by the background loop)
# ---------------------------------------------------------------------------

def in_window(now, start_str, end_str):
    """True if now (datetime) is within a daily [start,end) window. Handles
    windows that wrap past midnight. start==end means 'always on'."""
    try:
        s = datetime.time.fromisoformat(start_str)
        e = datetime.time.fromisoformat(end_str)
    except (ValueError, TypeError):
        return False
    t = now.time()
    if s == e:
        return True            # 24h window
    if s < e:
        return s <= t < e
    return t >= s or t < e     # wraps midnight

def is_blacked_out(date_str, now):
    """True if the display should be dark right now — a whole-day blackout,
    or a timed blackout window covering this moment."""
    schedule = load_json(SCHEDULE_CONFIG, {"entries": []})
    hhmm = now.strftime("%H:%M")
    for e in schedule.get("entries", []):
        if e.get("date") != date_str or e.get("type") != "blackout":
            continue
        st, en = e.get("start_time"), e.get("end_time")
        if not st and not en:
            return True                       # whole-day blackout
        if (st or "00:00") <= hhmm <= (en or "23:59"):
            return True                       # timed blackout window
    return False


# ---------------------------------------------------------------------------
# fpp-brightness plugin integration
# API: GET http://localhost/api/plugin-apis/Brightness/{value}
# Range 0-200 — 100 = normal, 0 = off, 200 = double
# ---------------------------------------------------------------------------

def _set_brightness(value, log_it=True):
    value = max(0, min(200, int(value)))
    try:
        urllib.request.urlopen(
            f"http://localhost/api/plugin-apis/Brightness/{value}", timeout=5
        )
        if log_it:
            log.info("Brightness set to %d", value)
    except Exception as e:
        log.warning("Brightness set failed (%d): %s", value, e)

def trigger_dim(cfg):
    """Dim lighting to the pre-show level (instant)."""
    _set_brightness(int(cfg.get("pre_show_brightness", 20)))

def trigger_dim_restore(cfg):
    """Snap lighting to the normal level."""
    _set_brightness(int(cfg.get("normal_brightness", 100)))

def audio_duration(path):
    """Length of an audio file in seconds via ffprobe, or None if unavailable."""
    if not os.path.isfile(path):
        return None
    try:
        out = subprocess.run(
            ["ffprobe", "-v", "quiet", "-show_entries", "format=duration",
             "-of", "csv=p=0", path],
            capture_output=True, text=True, timeout=10,
        ).stdout.strip()
        return float(out) if out else None
    except (FileNotFoundError, ValueError, subprocess.SubprocessError):
        return None


# ---------------------------------------------------------------------------
# Rotation state — tracks which show in a rotation group fires next
# ---------------------------------------------------------------------------

def next_rotation_show(rotation_key, show_ids):
    """Return the next show_id for a rotation group, advancing the index."""
    state = load_json(ROTATION_STATE, {"rotations": {}})
    rotations = state.setdefault("rotations", {})
    idx = rotations.get(rotation_key, {}).get("next_index", 0) % len(show_ids)
    rotations[rotation_key] = {"next_index": (idx + 1) % len(show_ids)}
    save_json(ROTATION_STATE, state)
    return show_ids[idx]


# ---------------------------------------------------------------------------
# Audio playback
# ---------------------------------------------------------------------------

def _play_audio(path, gain_db, timeout_secs):
    """Play an MP3/WAV with software gain boost. Tries ffmpeg then mpg123."""
    gain = 10 ** (gain_db / 20.0)

    # ffmpeg (preferred — supports gain + any format)
    cmd = [
        "ffmpeg", "-hide_banner", "-loglevel", "error",
        "-i", path,
        "-af", f"volume={gain:.4f}",
        "-f", "alsa", "default",
    ]
    try:
        proc = subprocess.Popen(cmd, stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL)
        try:
            proc.wait(timeout=timeout_secs)
            return
        except subprocess.TimeoutExpired:
            proc.kill()
            log.warning("Announcement timed out (ffmpeg): %s", path)
            return
    except FileNotFoundError:
        pass  # ffmpeg not installed

    # mpg123 fallback (no gain control, but works for MP3)
    try:
        proc = subprocess.Popen(
            ["mpg123", "-q", path],
            stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL,
        )
        try:
            proc.wait(timeout=timeout_secs)
        except subprocess.TimeoutExpired:
            proc.kill()
            log.warning("Announcement timed out (mpg123): %s", path)
    except FileNotFoundError:
        log.error("No audio player found (tried ffmpeg, mpg123)")


def play_announcement(audio_path, hw_cfg, an_cfg):
    """
    Duck music faders, play announcement, restore faders.
    Pauses bridge fader sync for the duration via PAUSE_SYNC_FLAG.
    Safe to call from any thread; catches all exceptions.
    """
    if not os.path.isfile(audio_path):
        log.warning("Announcement file not found: %s", audio_path)
        return

    duck_level   = float(hw_cfg.get("duck_level",    0.25))
    duck_fade    = float(hw_cfg.get("duck_fade_secs", 2.0))
    gain_db      = float(an_cfg.get("gain_db",        6.0))
    max_duration = int(an_cfg.get("max_duration_secs", 300))
    xr18_ip = hw_cfg.get("mixer_ip") or hw_cfg.get("xr18_ip") or "192.168.0.1"
    fch = hw_cfg.get("fader_channel")
    if fch is not None:
        ch1 = ch2 = str(int(fch)).zfill(2)
    else:
        ch1 = str(hw_cfg.get("music_ch1", "1")).zfill(2)
        ch2 = str(hw_cfg.get("music_ch2", "2")).zfill(2)

    # Read current fader from bridge state; fall back to FPP-volume-derived value
    try:
        normal_lvl = float(open(FADER_STATE_FILE).read().strip())
    except Exception:
        normal_lvl = fpp_get_volume() / 100.0

    log.info("Announcement: %s (duck %.0f%% → restore %.0f%%)",
             os.path.basename(audio_path), duck_level * 100, normal_lvl * 100)
    try:
        # Pause bridge so it doesn't fight us for fader control
        open(PAUSE_SYNC_FLAG, "w").close()

        _fade_faders(xr18_ip, ch1, ch2, normal_lvl, duck_level, duck_fade)
        _play_audio(audio_path, gain_db, max_duration)
        _fade_faders(xr18_ip, ch1, ch2, duck_level, normal_lvl, duck_fade)

    except Exception as e:
        log.error("Announcement error: %s", e)
    finally:
        # Always clean up the flag, even on crash
        try:
            os.remove(PAUSE_SYNC_FLAG)
        except FileNotFoundError:
            pass


# ---------------------------------------------------------------------------
# Schedule helpers
# ---------------------------------------------------------------------------

def expand_rules_for_date(rules, date_str):
    """Expand repeating rules into virtual show entries for date_str."""
    d = datetime.date.fromisoformat(date_str)
    js_dow = (d.weekday() + 1) % 7   # Python Mon=0 → Sun=0 like PHP date('w')
    shows = []
    for rule in rules:
        if date_str < rule.get('start_date', '') or date_str > rule.get('end_date', ''):
            continue
        if js_dow not in rule.get('days', [0, 1, 2, 3, 4, 5, 6]):
            continue
        window_start  = rule.get('window_start', '19:00')
        window_end    = rule.get('window_end')
        interval_mins = int(rule.get('interval_mins', 0))
        if window_end and interval_mins > 0:
            times = []
            base  = datetime.datetime.strptime('2000-01-01 ' + window_start, '%Y-%m-%d %H:%M')
            end_t = datetime.datetime.strptime('2000-01-01 ' + window_end,   '%Y-%m-%d %H:%M')
            t = base
            while t <= end_t:
                times.append(t.strftime('%H:%M'))
                t += datetime.timedelta(minutes=interval_mins)
        else:
            times = [window_start]
        for time_str in times:
            entry = {
                'id':      f"{rule['id']}|{date_str}|{time_str}",
                'date':    date_str,
                'type':    'show',
                'time':    time_str,
                'rule_id': rule['id'],
            }
            if 'playlist' in rule:    entry['playlist']  = rule['playlist']
            elif 'playlists' in rule: entry['playlists'] = rule['playlists']
            shows.append(entry)
    return shows


def system_disabled():
    """True while a kiosk/UI 'disable system until…' override is active."""
    due = load_json(OVERRIDES_CONFIG).get("disabled_until")
    if not due:
        return False
    try:
        return datetime.datetime.now() < datetime.datetime.fromisoformat(due)
    except ValueError:
        return False


def schedule_for_date(date_str):
    """
    Return sorted list of show entries for date_str, or None on blackout days.
    Includes both manual one-off entries and entries generated from repeating rules.
    """
    schedule = load_json(SCHEDULE_CONFIG, {"entries": [], "rules": []})
    entries  = schedule.get("entries", [])
    rules    = schedule.get("rules", [])

    blackouts = [e for e in entries if e["date"] == date_str and e.get("type") == "blackout"]
    # Whole-day blackout: any entry with no time range
    if any(not b.get("start_time") and not b.get("end_time") for b in blackouts):
        return None

    shows  = [e for e in entries if e["date"] == date_str and e.get("type") == "show"]
    shows += expand_rules_for_date(rules, date_str)
    # Filter shows that fall within a timed blackout window
    if blackouts:
        def _blacked(t):
            for b in blackouts:
                s, e = b.get("start_time", "00:00"), b.get("end_time", "23:59")
                if s <= t <= e:
                    return True
            return False
        shows = [s for s in shows if not _blacked(s["time"])]
    return sorted(shows, key=lambda e: e["time"])

def resolve_show_id(entry):
    """
    Return the show_id to use for this entry.
    Supports direct assignment (show_id) and rotation (rotation_ids list).
    """
    if "show_id" in entry:
        return entry["show_id"]
    ids = entry.get("rotation_ids", [])
    if not ids:
        return None
    key = ",".join(ids)
    return next_rotation_show(key, ids)

def get_show_def(show_id):
    shows = load_json(SHOWS_CONFIG, {"shows": []}).get("shows", [])
    return next((s for s in shows if s["id"] == show_id), None)


# ---------------------------------------------------------------------------
# Main scheduler class
# ---------------------------------------------------------------------------

class ShowScheduler:

    def __init__(self):
        self._stop     = threading.Event()
        self._fired    = set()       # "date|time|tag" keys already triggered today
        self._in_show  = threading.Event()
        self._last_daytime_announce = 0.0
        self._bg_effect = None       # (kind, name) of the effect we started, or None
        self._bg_lock   = threading.Lock()
        self._audio_dur = {}         # path -> duration cache (avoid re-probing)

    # ---- helpers -----------------------------------------------------------

    def _hw(self):  return load_json(HARDWARE_CONFIG)
    def _an(self):  return load_json(ANNOUNCE_CONFIG)
    def _bg(self):
        """Background config, with backward-compat: an old announcements
        'background_playlist' becomes an always-on music window."""
        cfg = load_json(BACKGROUND_CONFIG)
        if cfg:
            return cfg
        legacy = self._an().get("background_playlist", "")
        if legacy:
            return {"music": {"enabled": True, "playlist": legacy,
                              "start": "00:00", "end": "00:00"}}
        return {}

    def _fire_once(self, key, fn, *args):
        """Run fn(*args) in a daemon thread, but only once per key per day."""
        if key in self._fired:
            return
        self._fired.add(key)
        threading.Thread(target=self._safe(fn), args=args, daemon=True).start()

    def _safe(self, fn):
        """Wrap fn so exceptions are logged but don't kill the scheduler."""
        def wrapper(*args):
            try:
                fn(*args)
            except Exception as e:
                log.error("Unhandled error in %s: %s", fn.__name__, e)
        return wrapper

    def _set_level(self, hw_cfg, level):
        """Fade the music faders to a configured level (show_level / idle_level).
        No-op when the level or mixer IP is not configured."""
        if level is None:
            return
        ip = hw_cfg.get("mixer_ip") or hw_cfg.get("xr18_ip")
        if not ip:
            return
        fch = hw_cfg.get("fader_channel")
        if fch is not None:
            ch1 = ch2 = str(int(fch))
        else:
            ch1 = str(hw_cfg.get("music_ch1", "1"))
            ch2 = str(hw_cfg.get("music_ch2", "2"))
        try:
            cur = float(open(FADER_STATE_FILE).read().strip())
        except Exception:
            cur = fpp_get_volume() / 100.0
        _fade_faders(ip, ch1, ch2, cur, float(level), 2.0)
        log.info("Music fader level set to %.2f", float(level))

    # ---- pre-show brightness fade ------------------------------------------

    def _fade_window(self, an_cfg):
        """How many seconds before show start the brightness fade should begin.

        = length of the closest pre-show audio if one exists (fade starts when
        that audio would start), else the configured pre_show_fade_secs."""
        best = None
        folder = an_cfg.get("folder", ANNOUNCE_FOLDER)
        for pre in an_cfg.get("pre_show", []):
            fname = pre.get("file", "")
            if not fname:
                continue
            path = fname if os.path.isabs(fname) else os.path.join(folder, fname)
            off = float(pre.get("mins_before", 5))
            if best is None or off < best[0]:   # closest to show wins
                best = (off, path)
        if best:
            path = best[1]
            if path not in self._audio_dur:
                self._audio_dur[path] = audio_duration(path)
            dur = self._audio_dur[path]
            if dur and dur > 0:
                return dur
        return float(an_cfg.get("pre_show_fade_secs", 30))

    def _run_preshow_fade(self, show_dt, window_secs, an_cfg):
        """Fade brightness from normal down to the pre-show level, reaching the
        dim level right as the show starts. _run_show then snaps back to 100%."""
        normal = int(an_cfg.get("normal_brightness", 100))
        dim    = int(an_cfg.get("pre_show_brightness", 20))
        if dim >= normal or window_secs <= 0:
            return                      # nothing to dim
        today = show_dt.date().isoformat()
        log.info("Pre-show brightness fade: %d%% → %d%% over %.0fs", normal, dim, window_secs)
        _set_brightness(normal, log_it=False)
        while True:
            now = datetime.datetime.now()
            remaining = (show_dt - now).total_seconds()
            if remaining <= 0:
                break                   # show is starting — _run_show snaps to normal
            if (self._in_show.is_set() or is_show_running()
                    or system_disabled() or is_blacked_out(today, now)):
                _set_brightness(normal, log_it=False)   # aborted — leave it bright
                log.info("Pre-show fade aborted")
                return
            frac  = max(0.0, min(1.0, 1.0 - remaining / window_secs))
            level = round(normal + (dim - normal) * frac)
            _set_brightness(level, log_it=False)
            time.sleep(min(1.0, remaining))

    # ---- show runner -------------------------------------------------------

    def _run_show(self, entry):
        an_cfg = self._an()

        # Prefer playlist field set directly on the entry (new schema)
        playlist = entry.get("playlist")
        if not playlist:
            playlists = entry.get("playlists", [])
            if len(playlists) == 1:
                playlist = playlists[0]
            elif len(playlists) > 1:
                key = ",".join(playlists)
                playlist = next_rotation_show(key, playlists)

        if not playlist:
            # Legacy: look up show definition by show_id / rotation_ids
            show_id  = resolve_show_id(entry)
            show_def = get_show_def(show_id) if show_id else None
            if not show_def:
                log.error("No playlist for entry at %s %s", entry.get("date"), entry.get("time"))
                return
            playlist = show_def["playlist"]

        log.info("--- Show starting: %s at %s ---", playlist, entry.get("time", ""))
        hw_cfg = self._hw()
        # A scheduled show resumes normal operation — clear any lingering
        # manual-stop suppression so background returns after the show.
        try:
            os.remove(MANUAL_STOP_FLAG)
        except OSError:
            pass
        self._in_show.set()
        try:
            trigger_dim_restore(an_cfg)   # snap to full brightness as the show begins
            self._set_level(hw_cfg, hw_cfg.get("show_level"))
            if not fpp_start_playlist(playlist):
                log.error("Playlist start returned failure for '%s' — aborting show", playlist)
                return
            # Wait up to 30 s for FPP to report our playlist as current.
            # We track the specific playlist name (not global idle) so
            # looping background music can't fool end detection.
            started = False
            for _ in range(30):
                time.sleep(1)
                if fpp_current_playlist() == playlist:
                    started = True
                    break
            if not started:
                log.warning("FPP never reported '%s' as current playlist — aborting wait", playlist)
                return
            log.info("FPP confirmed playing: %s", playlist)
            # Wait for the show playlist to end; 2-hour cap as safety net
            max_wait = 7200
            waited   = 0
            while waited < max_wait:
                time.sleep(5)
                waited += 5
                if fpp_current_playlist() != playlist:
                    break
            log.info("--- Show ended: %s ---", playlist)
        finally:
            self._in_show.clear()
            trigger_dim_restore(an_cfg)
            self._set_level(hw_cfg, hw_cfg.get("idle_level"))
            # Hand back to scheduled background immediately (no silent gap). If a
            # Stop was pressed during the show, MANUAL_STOP_FLAG is now set and
            # _apply_background keeps things quiet until the next show.
            self._apply_background()

    # ---- background music + effects ----------------------------------------

    def _apply_background(self):
        """Bring FPP into the right background state for right now.

        Background MUSIC (audio) loops during its window only when FPP is idle
        (never over a show), and is silenced during blackouts — blackouts are
        the venue's quiet hours, which suppress audio and shows.

        Background EFFECT (lighting) loops as an overlay during its own window,
        suppressed only while a show runs (shows own their lighting). It is NOT
        suppressed by blackouts — the lights stay on during quiet hours.

        Both are suppressed by the system-disable override and a manual stop.
        Idempotent — safe to call from the loop and from show end."""
        with self._bg_lock:
            cfg = self._bg()
            now = datetime.datetime.now()
            today = now.date().isoformat()
            off = system_disabled() or os.path.exists(MANUAL_STOP_FLAG)  # everything off
            blacked = is_blacked_out(today, now)                          # audio off (quiet hours)
            show = self._in_show.is_set() or is_show_running()
            music  = cfg.get("music", {})
            effect = cfg.get("effect", {})
            status = {"music": None, "effect": None,
                      "music_enabled": bool(music.get("enabled")),
                      "effect_enabled": bool(effect.get("enabled"))}

            # --- background music (audio): idle only, silenced by blackout ---
            pl = music.get("playlist", "")
            want_music = bool(
                music.get("enabled") and pl and not off and not blacked and not show
                and in_window(now, music.get("start", "00:00"), music.get("end", "00:00"))
            )
            cur = fpp_current_playlist()
            if want_music:
                if cur != pl:
                    log.info("Background music → %s", pl)
                    fpp_start_playlist(pl, repeat=True)
                status["music"] = pl
            elif not show and pl and cur == pl:
                # Stop only OUR looping music (never interrupt a show)
                log.info("Background music stopping — %s", pl)
                fpp_stop()

            # --- background effect (lighting): overlay, ignores blackout ---
            eff  = effect.get("effect", "")
            kind = effect.get("type", "eseq")
            want_effect = bool(
                effect.get("enabled") and eff and not off and not show
                and in_window(now, effect.get("start", "00:00"), effect.get("end", "00:00"))
            )
            if want_effect:
                if self._bg_effect != (kind, eff):
                    if self._bg_effect:
                        fpp_stop_effect(self._bg_effect[1], self._bg_effect[0])
                    fpp_start_effect(eff, kind)
                    self._bg_effect = (kind, eff)
                status["effect"] = eff
            elif self._bg_effect:
                fpp_stop_effect(self._bg_effect[1], self._bg_effect[0])
                self._bg_effect = None

            save_json(BG_STATUS_FILE, status)

    def _background_loop(self):
        while not self._stop.wait(45):
            try:
                self._apply_background()
            except Exception as e:
                log.error("Background loop error: %s", e)

    # ---- announcement runner -----------------------------------------------

    def _run_announcement(self, audio_path):
        if self._in_show.is_set():
            log.info("Show running — skipping announcement: %s", audio_path)
            return
        if is_show_running():
            log.info("FPP show detected — skipping announcement: %s", audio_path)
            return
        play_announcement(audio_path, self._hw(), self._an())

    # ---- schedule loop -----------------------------------------------------

    def _schedule_loop(self):
        last_day = None

        while not self._stop.wait(30):
            try:
                now   = datetime.datetime.now()
                today = now.date().isoformat()

                if today != last_day:
                    self._fired.clear()
                    log.info("New day: %s", today)
                    last_day = today

                if system_disabled():
                    continue

                todays_shows = schedule_for_date(today)
                if todays_shows is None:
                    continue  # blackout day

                an_cfg = self._an()
                pre_show_announcements = an_cfg.get("pre_show", [])

                for entry in todays_shows:
                    show_time = datetime.datetime.strptime(
                        f"{today} {entry['time']}", "%Y-%m-%d %H:%M"
                    )
                    delta_mins = (show_time - now).total_seconds() / 60.0

                    # Pre-show announcements (e.g. 15, 10, 5 min before)
                    for pre in pre_show_announcements:
                        offset = float(pre.get("mins_before", 5))
                        fname  = pre.get("file", "")
                        if not fname:
                            continue
                        key = f"{today}|{entry['time']}|pre_{offset}"
                        # Fire when within a 30-second window of the target offset
                        if abs(delta_mins - offset) <= 0.5 and not self._in_show.is_set():
                            folder = an_cfg.get("folder", ANNOUNCE_FOLDER)
                            self._fire_once(key, self._run_announcement,
                                            os.path.join(folder, fname))

                    # Pre-show brightness fade — begins one fade-window before
                    # the show and reaches the dim level right as it starts.
                    delta_secs = delta_mins * 60.0
                    if delta_secs > 0.5 and not self._in_show.is_set():
                        window = self._fade_window(an_cfg)
                        if delta_secs <= window:
                            fkey = f"{today}|{entry['time']}|fade"
                            self._fire_once(fkey, self._run_preshow_fade,
                                            show_time, window, an_cfg)

                    # Show start
                    key = f"{today}|{entry['time']}|show"
                    if -0.5 <= delta_mins <= 0.5 and not self._in_show.is_set():
                        self._fire_once(key, self._run_show, entry)

            except Exception as e:
                log.error("Schedule loop error: %s", e)

    # ---- daytime announcement loop -----------------------------------------

    def _daytime_loop(self):
        while not self._stop.wait(60):
            try:
                an_cfg  = self._an()
                daytime = an_cfg.get("daytime", {})

                if not daytime.get("enabled", False):
                    continue

                now      = datetime.datetime.now()
                start_t  = datetime.time.fromisoformat(daytime.get("start", "10:00"))
                end_t    = datetime.time.fromisoformat(daytime.get("end",   "18:00"))
                interval = float(daytime.get("interval_mins", 20)) * 60
                folder   = daytime.get("folder", os.path.join(ANNOUNCE_FOLDER, "daytime"))

                if not (start_t <= now.time() <= end_t):
                    continue
                if (time.time() - self._last_daytime_announce) < interval:
                    continue
                if self._in_show.is_set() or is_show_running() or system_disabled():
                    continue

                # Check nothing is too close to a scheduled show
                today_shows = schedule_for_date(now.date().isoformat()) or []
                for entry in today_shows:
                    show_time = datetime.datetime.strptime(
                        f"{now.date().isoformat()} {entry['time']}", "%Y-%m-%d %H:%M"
                    )
                    mins_until = (show_time - now).total_seconds() / 60.0
                    # Don't start a daytime announcement within 20 min of a show
                    if 0 < mins_until < 20:
                        log.info("Daytime announce suppressed — show in %.0f min", mins_until)
                        break
                else:
                    files = sorted(glob.glob(os.path.join(folder, "*.mp3")))
                    if not files:
                        files = sorted(glob.glob(os.path.join(ANNOUNCE_FOLDER, "*.mp3")))
                    if files:
                        chosen = random.choice(files)
                        self._last_daytime_announce = time.time()
                        threading.Thread(
                            target=self._safe(self._run_announcement),
                            args=(chosen,), daemon=True,
                        ).start()

            except Exception as e:
                log.error("Daytime loop error: %s", e)

    # ---- stale flag watchdog -----------------------------------------------

    def _watchdog_loop(self):
        """Remove the pause-sync flag if it's been there for >5 minutes (crash recovery)."""
        while not self._stop.wait(60):
            try:
                if os.path.exists(PAUSE_SYNC_FLAG):
                    age = time.time() - os.path.getmtime(PAUSE_SYNC_FLAG)
                    if age > 300:
                        os.remove(PAUSE_SYNC_FLAG)
                        log.warning("Removed stale pause-sync flag (age %.0fs)", age)
            except Exception:
                pass

    # ---- entry point -------------------------------------------------------

    def run(self):
        log.info("XR18 Show Scheduler starting")
        ensure_fpp_scheduler_enabled()
        threads = [
            threading.Thread(target=self._schedule_loop,   daemon=True, name="schedule"),
            threading.Thread(target=self._daytime_loop,    daemon=True, name="daytime"),
            threading.Thread(target=self._background_loop, daemon=True, name="background"),
            threading.Thread(target=self._watchdog_loop,   daemon=True, name="watchdog"),
        ]
        for t in threads:
            t.start()
        for t in threads:
            t.join()


LOCK_PATH = "/tmp/showmanager_scheduler.lock"

def acquire_single_instance():
    """Exclusive lock so only one scheduler ever runs. Without this, a second
    copy (e.g. a restart racing the boot launch) double-fires every show,
    announcement, and background action. The lock releases automatically when
    the holding process dies."""
    handle = open(LOCK_PATH, "w")
    try:
        fcntl.flock(handle, fcntl.LOCK_EX | fcntl.LOCK_NB)
    except OSError:
        log.warning("Another scheduler is already running — exiting this copy")
        return None
    handle.write(str(os.getpid()))
    handle.flush()
    return handle   # keep the fd open for the process lifetime to hold the lock


if __name__ == "__main__":
    _lock = acquire_single_instance()
    if _lock is None:
        raise SystemExit(0)
    ShowScheduler().run()

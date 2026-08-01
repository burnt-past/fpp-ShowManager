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
import logging.handlers
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
DROPBOX_CONFIG   = "/home/fpp/media/config/ShowManagerDropbox.config"
PAUSE_SYNC_FLAG  = "/tmp/xr18_pause_sync"
FADER_STATE_FILE = "/tmp/xr18_current_fader"
BG_STATUS_FILE   = "/tmp/showmanager_bg_status.json"
RUN_NOW_FILE     = "/tmp/showmanager_run_now"
LOG_PATH         = "/home/fpp/media/logs/showmanager.log"

XR18_PORT        = 10024
ANNOUNCE_FOLDER  = os.path.join(os.path.dirname(os.path.dirname(__file__)), "announcements")

# Rotate the log at ~2 MB, keep 3 old copies — bounded disk use on the Pi's
# SD card, no external cron needed.
_handler = logging.handlers.RotatingFileHandler(
    LOG_PATH, maxBytes=2_000_000, backupCount=3
)
_handler.setFormatter(logging.Formatter("%(asctime)s %(levelname)s %(message)s"))
logging.basicConfig(level=logging.INFO, handlers=[_handler])
log = logging.getLogger(__name__)


# ---------------------------------------------------------------------------
# Config helpers
# ---------------------------------------------------------------------------

_warned_bad_configs = set()   # paths we've already warned about (avoid log spam)

def load_json(path, default=None):
    fallback = {} if default is None else default
    try:
        with open(path) as f:
            data = json.load(f)
    except FileNotFoundError:
        _warned_bad_configs.discard(path)
        return fallback
    except Exception as e:
        log.error("Config load error %s: %s", path, e)
        return fallback
    # Every config here is a JSON object; guard against a file that somehow
    # holds an array (or other type) so callers can safely .get() on it.
    if not isinstance(data, dict):
        if path not in _warned_bad_configs:   # log once, not every cycle
            log.warning("Config %s is not a JSON object (got %s) — ignoring it; "
                        "reset it with: echo '{}' > %s", path, type(data).__name__, path)
            _warned_bad_configs.add(path)
        return fallback
    _warned_bad_configs.discard(path)          # recovered — allow future warnings
    return data

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

def _fade_faders(ip, ch1, ch2, from_lvl, to_lvl, duration_secs, rate_hz=50):
    """Ramp the two music faders smoothly. Step count is derived from the
    duration at a fixed update rate (default 50 Hz), so the fade stays fine
    at any length instead of a fixed 20 chunky steps."""
    duration_secs = max(0.0, float(duration_secs))
    steps = max(1, int(round(duration_secs * rate_hz)))
    delay = duration_secs / steps
    a1 = f"/ch/{ch1.zfill(2)}/mix/fader"
    a2 = f"/ch/{ch2.zfill(2)}/mix/fader"
    for i in range(steps + 1):
        t   = i / steps
        lvl = float(from_lvl + (to_lvl - from_lvl) * t)
        _send_osc(ip, a1, lvl)
        _send_osc(ip, a2, lvl)
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
    """True when FPP is playing an actual SHOW playlist — i.e. a playlist that
    is neither idle nor the looping background-music playlist.

    We key off the *playlist*, not current_sequence: a background FSEQ effect
    plays a sequence as an overlay with no playlist, so keying off the sequence
    made the standby effect look like a show — reporting the effect as 'Idle',
    suppressing background music, and blocking announcements."""
    cur = fpp_current_playlist()
    if not cur:
        return False
    music = load_json(BACKGROUND_CONFIG).get("music") or {}
    return cur != music.get("playlist", "")

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

def _play_audio(path, gain_db, timeout_secs, device="default"):
    """Play an MP3/WAV with software gain to a specific ALSA device, flattened
    to MONO but emitted as DUAL-MONO (the mono content on BOTH L and R). Voice
    stays centred, and it doesn't matter which side of the output feeds the
    mixer input — a plain `-ac 1` lands on only one channel on some codecs.
    Tries ffmpeg then mpg123."""
    gain = 10 ** (gain_db / 20.0)
    dev  = device or "default"

    # ffmpeg (preferred). Chain: gain → normalise to stereo → sum to dual-mono.
    # aformat first so a mono *or* stereo source both become 2-channel before
    # the pan sums them onto both outputs.
    af = (f"volume={gain:.4f},aformat=channel_layouts=stereo,"
          "pan=stereo|c0<c0+c1|c1<c0+c1")
    cmd = [
        "ffmpeg", "-hide_banner", "-loglevel", "error",
        "-i", path,
        "-af", af,
        "-f", "alsa", dev,
    ]
    try:
        proc = subprocess.Popen(cmd, stdout=subprocess.DEVNULL, stderr=subprocess.PIPE)
        try:
            _, err = proc.communicate(timeout=timeout_secs)
        except subprocess.TimeoutExpired:
            proc.kill()
            log.warning("Announcement timed out (ffmpeg): %s", path)
            return
        if proc.returncode == 0:
            return
        # Non-zero exit (e.g. device busy or a bad filter) — log and try mpg123
        log.warning("ffmpeg announcement failed (%s): %s", proc.returncode,
                    (err or b"").decode(errors="replace").strip()[:200])
    except FileNotFoundError:
        pass  # ffmpeg not installed

    # mpg123 fallback (MP3 only). Play stereo so both channels carry audio;
    # -a targets the ALSA device.
    mpg = ["mpg123", "-q"]
    if dev and dev != "default":
        mpg += ["-o", "alsa", "-a", dev]
    mpg.append(path)
    try:
        proc = subprocess.Popen(mpg, stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL)
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
    device       = hw_cfg.get("announce_device", "default")
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
        _play_audio(audio_path, gain_db, max_duration, device)
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
        self._music_level = None     # current music-fader level we've set (plugin owns it)

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
        """Fade the music faders to a configured level (show / idle / background).
        No-op when the level or mixer IP is not configured."""
        if level is None or level == "":
            return
        try:
            level = float(level)
        except (TypeError, ValueError):
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
        # The plugin owns the music fader. The shared fader file is the source of
        # truth for the current level — both this scheduler and the Status page's
        # live slider write it — so a fade starts from wherever it actually is.
        try:
            cur = float(open(FADER_STATE_FILE).read().strip())
        except Exception:
            cur = self._music_level if self._music_level is not None else fpp_get_volume() / 100.0
        _fade_faders(ip, ch1, ch2, cur, level, 2.0)
        self._music_level = level
        try:
            with open(FADER_STATE_FILE, "w") as f:
                f.write(f"{level:.4f}")   # keep the Status/kiosk fader readout live
        except Exception:
            pass
        log.info("Music fader level set to %.2f", level)

    # ---- pre-show brightness fade ------------------------------------------

    def _fade_lead_secs(self, an_cfg):
        """How many seconds before show start the brightness fade should BEGIN.

        The fade DURATION is always pre_show_fade_secs. The fade START is either
        the fade time before the show (default), or when a selected pre-show
        audio begins (that announcement's mins_before)."""
        fade_secs = float(an_cfg.get("pre_show_fade_secs", 30))
        anchor = an_cfg.get("fade_anchor_mins")
        if anchor not in (None, ""):
            try:
                mins = float(anchor)
            except (TypeError, ValueError):
                mins = None
            if mins is not None:
                for pre in an_cfg.get("pre_show", []):
                    if pre.get("file") and abs(float(pre.get("mins_before", 5)) - mins) < 0.01:
                        return mins * 60.0   # fade begins when that audio starts
        return fade_secs

    def _run_preshow_fade(self, show_dt, an_cfg):
        """Fade brightness from normal down to the pre-show level over the fade
        time, then hold dim until the show starts. _run_show snaps back to
        normal (after clearing the background effect)."""
        normal    = int(an_cfg.get("normal_brightness", 100))
        dim       = int(an_cfg.get("pre_show_brightness", 20))
        fade_secs = float(an_cfg.get("pre_show_fade_secs", 30))
        if dim >= normal or fade_secs <= 0:
            return                      # nothing to dim
        today = show_dt.date().isoformat()
        fade_start = datetime.datetime.now()
        log.info("Pre-show brightness fade: %d%% → %d%% over %.0fs", normal, dim, fade_secs)
        _set_brightness(normal, log_it=False)
        while True:
            now = datetime.datetime.now()
            if now >= show_dt:
                break                   # show is starting — _run_show takes over
            if (self._in_show.is_set() or is_show_running()
                    or system_disabled() or is_blacked_out(today, now)):
                _set_brightness(normal, log_it=False)   # aborted — leave it bright
                log.info("Pre-show fade aborted")
                return
            elapsed = (now - fade_start).total_seconds()
            frac  = max(0.0, min(1.0, elapsed / fade_secs))   # holds at dim past fade_secs
            level = round(normal + (dim - normal) * frac)
            _set_brightness(level, log_it=False)
            time.sleep(min(1.0, (show_dt - now).total_seconds()))

    def _run_postshow_fade(self, an_cfg):
        """When a show ends: snap brightness to 0, then fade back up to the
        normal level over post_show_fade_secs. With no fade time configured,
        snap straight to normal (old behavior)."""
        normal    = int(an_cfg.get("normal_brightness", 100))
        try:
            fade_secs = float(an_cfg.get("post_show_fade_secs", 0))
        except (TypeError, ValueError):
            fade_secs = 0.0
        if fade_secs <= 0:
            trigger_dim_restore(an_cfg)   # snap to normal
            return
        log.info("Post-show brightness fade: 0%% → %d%% over %.0fs", normal, fade_secs)
        _set_brightness(0, log_it=False)
        fade_start = datetime.datetime.now()
        while True:
            now = datetime.datetime.now()
            # A new show (or manual re-dim) takes over — stop fading.
            if self._in_show.is_set() or is_show_running():
                return
            elapsed = (now - fade_start).total_seconds()
            frac  = max(0.0, min(1.0, elapsed / fade_secs))
            _set_brightness(round(normal * frac), log_it=False)
            if frac >= 1.0:
                break
            time.sleep(min(1.0, fade_secs - elapsed))

    # ---- show runner -------------------------------------------------------

    def _delayed_run_show(self, delay, entry):
        """Wait out the seconds remaining until show time, then run the show.
        The schedule loop only ticks every 30 s, so it catches a show in the
        tick BEFORE its start time; sleeping the remainder makes the show start
        on time instead of up to a full poll interval early."""
        if delay > 0 and self._stop.wait(delay):
            return                      # shutting down
        now = datetime.datetime.now()
        today = now.date().isoformat()
        if system_disabled() or is_blacked_out(today, now):
            log.info("Show suppressed at start time (disabled/blackout): %s",
                     entry.get("playlist") or entry.get("time"))
            return
        if self._in_show.is_set() or is_show_running():
            return
        self._run_show(entry)

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
        self._in_show.set()
        try:
            # Kill the background overlay effect first — only once it's gone do
            # we snap to full brightness, so the show's own lighting takes over
            # cleanly (no bright flash of the leftover overlay).
            with self._bg_lock:
                if self._bg_effect:
                    fpp_stop_effect(self._bg_effect[1], self._bg_effect[0])
                    self._bg_effect = None
                    time.sleep(0.5)   # let FPP drop the overlay before the snap
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
            self._set_level(hw_cfg, hw_cfg.get("idle_level"))
            # Hand back to scheduled background first (no silent gap) so any
            # overlay effect is running before we fade the lights up. A manual
            # Stop ends here too — background simply resumes per its schedule.
            self._apply_background()
            # Snap lights to 0 and fade back to normal over the configured
            # post-show fade time (or snap straight to normal when unset).
            self._run_postshow_fade(an_cfg)

    # ---- background music + effects ----------------------------------------

    def _apply_background(self):
        """Bring FPP into the right background state for right now.

        Background MUSIC (audio) loops during its window only when FPP is idle
        (never over a show), and is silenced during blackouts — blackouts are
        the venue's quiet hours, which suppress audio and shows.

        Background EFFECT (lighting) loops as an overlay during its own window,
        suppressed only while a show runs (shows own their lighting). It is NOT
        suppressed by blackouts — the lights stay on during quiet hours.

        Both are suppressed by the system-disable override. A manual Stop only
        stops the current playback — background then resumes per its schedule.
        Idempotent — safe to call from the loop and from show end."""
        with self._bg_lock:
            cfg = self._bg()
            hw_cfg = self._hw()
            now = datetime.datetime.now()
            today = now.date().isoformat()
            off = system_disabled()                                      # everything off
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
                    # Set the music faders to the background level before we
                    # start looping — background music is usually quieter than
                    # a show. Falls back to the current fader when unset.
                    self._set_level(hw_cfg, music.get("level"))
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
                    # Stop any existing copy of this effect first — a background
                    # effect keeps running in fppd across scheduler restarts, so
                    # starting without stopping would stack two instances.
                    fpp_stop_effect(eff, kind)
                    time.sleep(0.3)   # let fppd drop the old instance before restart
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

                    # Pre-show brightness fade — begins at its lead time before
                    # the show (fade time, or a selected pre-show audio's start).
                    delta_secs = delta_mins * 60.0
                    if delta_secs > 0.5 and not self._in_show.is_set():
                        lead = self._fade_lead_secs(an_cfg)
                        if delta_secs <= lead:
                            fkey = f"{today}|{entry['time']}|fade"
                            self._fire_once(fkey, self._run_preshow_fade,
                                            show_time, an_cfg)

                    # Show start — fire at show time, never early. Catch the
                    # show in the tick just before (delta up to ~33 s ahead)
                    # and wait out the remainder so it starts on the second.
                    key = f"{today}|{entry['time']}|show"
                    if (key not in self._fired and not self._in_show.is_set()
                            and -1.0 <= delta_mins <= 0.55):
                        self._fired.add(key)
                        delay = (show_time - datetime.datetime.now()).total_seconds()
                        threading.Thread(
                            target=self._safe(self._delayed_run_show),
                            args=(max(0.0, delay), entry), daemon=True,
                        ).start()

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

    # ---- nightly cloud backup ----------------------------------------------

    def _cloud_backup_loop(self):
        """Once a day (after 04:00), if Dropbox auto-backup is enabled, upload a
        backup by calling the plugin's own dropbox_backup endpoint — reusing the
        PHP implementation so the logic lives in one place."""
        state_file = "/tmp/showmanager_cloud_backup_day"
        plugin_name = os.path.basename(
            os.path.dirname(os.path.dirname(os.path.abspath(__file__))))
        while not self._stop.wait(300):
            try:
                cfg = load_json(DROPBOX_CONFIG)
                if not cfg.get("auto") or not cfg.get("refresh_token"):
                    continue
                now = datetime.datetime.now()
                if now.hour < 4:
                    continue
                today = now.date().isoformat()
                try:
                    if open(state_file).read().strip() == today:
                        continue
                except OSError:
                    pass
                url = ("http://localhost/plugin.php?plugin=%s&page=ajax.php"
                       "&nopage=1&action=dropbox_backup" % plugin_name)
                try:
                    resp = urllib.request.urlopen(url, timeout=90).read().decode()[:200]
                    log.info("Nightly Dropbox backup: %s", resp)
                    with open(state_file, "w") as f:
                        f.write(today)   # only stamp on success, so failures retry
                except Exception as e:
                    log.warning("Nightly Dropbox backup failed: %s", e)
            except Exception as e:
                log.error("Cloud backup loop error: %s", e)

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

    # ---- run-now (manual full-pipeline show) -------------------------------

    def _runnow_loop(self):
        """Watch for a run-now request written by the UI and run that playlist
        through the full show pipeline (brightness snap, fader levels, effect
        kill, end detection, post-show fade) — the same path a scheduled show
        takes, just fired on demand."""
        while not self._stop.wait(3):
            try:
                if not os.path.exists(RUN_NOW_FILE):
                    continue
                try:
                    req = json.load(open(RUN_NOW_FILE))
                finally:
                    try:
                        os.remove(RUN_NOW_FILE)   # consume the request either way
                    except OSError:
                        pass
                playlist = (req or {}).get("playlist", "")
                if not playlist:
                    continue
                if self._in_show.is_set() or is_show_running():
                    log.info("Run-now ignored — a show is already running")
                    continue
                log.info("Run-now request: %s", playlist)
                entry = {"playlist": playlist,
                         "time": datetime.datetime.now().strftime("%H:%M"),
                         "date": datetime.date.today().isoformat()}
                threading.Thread(target=self._safe(self._run_show),
                                 args=(entry,), daemon=True).start()
            except Exception as e:
                log.error("Run-now loop error: %s", e)

    # ---- entry point -------------------------------------------------------

    def run(self):
        log.info("XR18 Show Scheduler starting")
        # Drop any stale run-now request so a queued-while-stopped show can't
        # fire a surprise on startup — only requests made while running count.
        try:
            os.remove(RUN_NOW_FILE)
        except OSError:
            pass
        ensure_fpp_scheduler_enabled()
        threads = [
            threading.Thread(target=self._schedule_loop,   daemon=True, name="schedule"),
            threading.Thread(target=self._daytime_loop,    daemon=True, name="daytime"),
            threading.Thread(target=self._background_loop, daemon=True, name="background"),
            threading.Thread(target=self._watchdog_loop,   daemon=True, name="watchdog"),
            threading.Thread(target=self._runnow_loop,     daemon=True, name="runnow"),
            threading.Thread(target=self._cloud_backup_loop, daemon=True, name="cloudbackup"),
        ]
        for t in threads:
            t.start()
        for t in threads:
            t.join()


LOCK_PATH = "/tmp/showmanager_scheduler.lock"

def _open_lock_file(path):
    """Open the lock file tolerantly. The file may already exist owned by a
    different user (e.g. created by root at boot, then reopened by the web
    user when 'Restart Scheduler' launches us) — so if we can't open it
    read-write, fall back to read-only, which is still enough for flock().
    On create, make it world-writable so any launcher can reuse it."""
    try:
        fd = os.open(path, os.O_RDWR | os.O_CREAT, 0o666)
        try:
            os.chmod(path, 0o666)   # only succeeds if we own it; harmless otherwise
        except OSError:
            pass
        return os.fdopen(fd, "r+")
    except PermissionError:
        try:
            return open(path, "r")  # flock works on a read-only fd too
        except OSError:
            return None

def acquire_single_instance():
    """Exclusive lock so only one scheduler ever runs. Without this, a second
    copy (e.g. a restart racing the boot launch) double-fires every show,
    announcement, and background action. The lock releases automatically when
    the holding process dies."""
    handle = _open_lock_file(LOCK_PATH)
    if handle is None:
        log.warning("Could not open lock file %s — starting without a lock", LOCK_PATH)
        return True   # don't refuse to run just because the lock file is unopenable
    try:
        fcntl.flock(handle, fcntl.LOCK_EX | fcntl.LOCK_NB)
    except OSError:
        log.warning("Another scheduler is already running — exiting this copy")
        return None
    try:
        handle.seek(0)
        handle.truncate()
        handle.write(str(os.getpid()))
        handle.flush()
    except (OSError, ValueError):
        pass          # read-only fd (someone else owns the file) — PID is cosmetic
    return handle   # keep the fd open for the process lifetime to hold the lock


if __name__ == "__main__":
    _lock = acquire_single_instance()
    if _lock is None:
        raise SystemExit(0)
    ShowScheduler().run()

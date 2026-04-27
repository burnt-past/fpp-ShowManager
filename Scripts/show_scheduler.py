#!/usr/bin/env python3
"""
XR18 Show Scheduler for Falcon Pi Player

Manages show scheduling, pre-show announcements, daytime announcements,
and background music resume. Coordinates with xr18_bridge.py via two
flag/state files:
  /tmp/xr18_pause_sync        — created to pause bridge fader sync
  /tmp/xr18_current_fader     — bridge writes current fader level here

Config files (all in /home/fpp/media/config/):
  ShowManager.config    — hardware settings (IP, channels, duck level)
  ShowManagerShows.config            — show definitions
  ShowManagerSchedule.config         — calendar entries (shows + blackout dates)
  ShowManagerAnnouncements.config    — announcement settings
"""

import datetime
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
PAUSE_SYNC_FLAG  = "/tmp/xr18_pause_sync"
FADER_STATE_FILE = "/tmp/xr18_current_fader"
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
    url = f"http://localhost{path}"
    try:
        req = urllib.request.Request(url, method=method)
        if body:
            req.add_header("Content-Type", "application/json")
            req.data = json.dumps(body).encode()
        with urllib.request.urlopen(req, timeout=timeout) as r:
            data = r.read().strip()
            if not data:
                return True  # HTTP success, empty body
            try:
                return json.loads(data)
            except ValueError:
                log.debug("FPP non-JSON response for %s: %s", path, data[:120])
                return True
    except urllib.error.HTTPError as e:
        log.warning("FPP API HTTP %d %s %s: %s", e.code, method, path, e.read()[:120])
        return None
    except Exception as e:
        log.warning("FPP API %s %s: %s", method, path, e)
        return None

def fpp_status():
    return _fpp("/api/fppd/status") or {}

def fpp_start_playlist(name, repeat=0):
    enc = urllib.parse.quote(name)
    result = _fpp(f"/api/playlist/{enc}/start?repeat={repeat}")
    if result is None:
        log.error("Failed to start playlist '%s' — HTTP/network error", name)
        return False
    # FPP may return {"Status":"FAILED",...} with HTTP 200
    if isinstance(result, dict):
        status = result.get("Status", result.get("status", ""))
        if str(status).upper() in ("FAILED", "ERROR", "false", "0"):
            log.error("FPP rejected playlist start '%s': %s", name, result)
            return False
        log.info("Started FPP playlist '%s' — FPP response: %s", name, result)
    else:
        log.info("Started FPP playlist '%s' — response: %s", name, result)
    return True

def fpp_stop():
    _fpp("/api/fppd/stop")
    log.info("FPP stop requested")

def fpp_get_volume():
    data = _fpp("/api/system/volume")
    return int(data.get("volume", 75)) if data else 75

def is_show_running():
    """True only when an FSEQ sequence is actively playing (not background music)."""
    return bool(fpp_status().get("current_sequence", ""))

def is_fpp_idle():
    st = fpp_status()
    status_num  = st.get("status", 0)
    status_name = st.get("status_name", "?")
    log.info("FPP idle check: status=%s status_name=%s", status_num, status_name)
    # FPP: status 0 = idle, 1 = playing
    return status_num == 0


# ---------------------------------------------------------------------------
# fpp-brightness plugin integration
# API: GET http://localhost/api/plugin-apis/Brightness/{value}
# Range 0-200 — 100 = normal, 0 = off, 200 = double
# ---------------------------------------------------------------------------

def _set_brightness(value):
    value = max(0, min(200, int(value)))
    try:
        urllib.request.urlopen(
            f"http://localhost/api/plugin-apis/Brightness/{value}", timeout=5
        )
        log.info("Brightness set to %d", value)
    except Exception as e:
        log.warning("Brightness set failed (%d): %s", value, e)

def trigger_dim(cfg):
    """Dim lighting before a show using the fpp-brightness plugin."""
    level = int(cfg.get("pre_show_brightness", 20))
    _set_brightness(level)

def trigger_dim_restore(cfg):
    """Restore lighting to normal after a show ends."""
    level = int(cfg.get("normal_brightness", 100))
    _set_brightness(level)


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


def schedule_for_date(date_str):
    """
    Return sorted list of show entries for date_str, or None on blackout days.
    Includes both manual one-off entries and entries generated from repeating rules.
    """
    schedule = load_json(SCHEDULE_CONFIG, {"entries": [], "rules": []})
    entries  = schedule.get("entries", [])
    rules    = schedule.get("rules", [])

    if any(e["date"] == date_str and e.get("type") == "blackout" for e in entries):
        return None

    shows  = [e for e in entries if e["date"] == date_str and e.get("type") == "show"]
    shows += expand_rules_for_date(rules, date_str)
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

    # ---- helpers -----------------------------------------------------------

    def _hw(self):  return load_json(HARDWARE_CONFIG)
    def _an(self):  return load_json(ANNOUNCE_CONFIG)

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
        self._in_show.set()
        try:
            trigger_dim(an_cfg)
            if not fpp_start_playlist(playlist):
                log.error("Playlist start returned failure for '%s' — aborting show", playlist)
                return
            # Wait up to 15 s for FPP to actually enter playing state
            playing = False
            for _ in range(15):
                time.sleep(1)
                if not is_fpp_idle():
                    playing = True
                    log.info("FPP confirmed playing: %s", playlist)
                    break
            if not playing:
                log.warning("FPP still idle 15 s after start — may not have loaded '%s'", playlist)
            # Wait for show to end; 2-hour cap as safety net
            max_wait = 7200
            waited   = 0
            while waited < max_wait:
                time.sleep(5)
                waited += 5
                if is_fpp_idle():
                    break
            log.info("--- Show ended: %s ---", playlist)
        finally:
            self._in_show.clear()
            trigger_dim_restore(an_cfg)
            bg = an_cfg.get("background_playlist", "")
            if bg:
                log.info("Resuming background music: %s", bg)
                fpp_start_playlist(bg, repeat=1)

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
                if self._in_show.is_set() or is_show_running():
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
        threads = [
            threading.Thread(target=self._schedule_loop, daemon=True, name="schedule"),
            threading.Thread(target=self._daytime_loop,  daemon=True, name="daytime"),
            threading.Thread(target=self._watchdog_loop, daemon=True, name="watchdog"),
        ]
        for t in threads:
            t.start()
        for t in threads:
            t.join()


if __name__ == "__main__":
    ShowScheduler().run()

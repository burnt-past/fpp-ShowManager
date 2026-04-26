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

HARDWARE_CONFIG  = "/home/fpp/media/config/ShowManager.config"
SHOWS_CONFIG     = "/home/fpp/media/config/ShowManagerShows.config"
SCHEDULE_CONFIG  = "/home/fpp/media/config/ShowManagerSchedule.config"
ANNOUNCE_CONFIG  = "/home/fpp/media/config/ShowManagerAnnouncements.config"
ROTATION_STATE   = "/home/fpp/media/config/ShowManagerRotation.config"
PAUSE_SYNC_FLAG  = "/tmp/xr18_pause_sync"
FADER_STATE_FILE = "/tmp/xr18_current_fader"
LOG_PATH         = "/home/fpp/media/logs/showmanager.log"

XR18_PORT        = 10024
ANNOUNCE_FOLDER  = "/home/fpp/media/plugins/ShowManager/announcements"

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
            return json.loads(r.read())
    except Exception as e:
        log.warning("FPP API %s %s: %s", method, path, e)
        return None

def fpp_status():
    return _fpp("/api/fppd/status") or {}

def fpp_start_playlist(name, repeat=0):
    enc = urllib.parse.quote(name)
    result = _fpp(f"/api/playlist/{enc}/start?repeat={repeat}")
    if result is not None:
        log.info("Started FPP playlist: %s", name)
    else:
        log.error("Failed to start playlist: %s", name)

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
    return fpp_status().get("status_name", "idle") == "idle"


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
    xr18_ip      = hw_cfg.get("xr18_ip",   "192.168.0.1")
    ch1          = hw_cfg.get("music_ch1", "01")
    ch2          = hw_cfg.get("music_ch2", "02")

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

def schedule_for_date(date_str):
    """
    Return sorted list of show entries for date_str, or None on blackout days.
    Each entry: {id, date, type:'show', time, show_id or rotation_ids:[...]}
    """
    entries = load_json(SCHEDULE_CONFIG, {"entries": []}).get("entries", [])

    if any(e["date"] == date_str and e.get("type") == "blackout" for e in entries):
        return None

    shows = [e for e in entries if e["date"] == date_str and e.get("type") == "show"]
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
        show_id  = resolve_show_id(entry)
        show_def = get_show_def(show_id) if show_id else None
        an_cfg   = self._an()

        if not show_def:
            log.error("No show definition for id=%s", show_id)
            return

        log.info("--- Show starting: %s (%s) ---", show_def["name"], show_def["playlist"])
        self._in_show.set()

        try:
            # Dim lighting via external plugin
            trigger_dim(an_cfg)

            # Trigger the show playlist
            fpp_start_playlist(show_def["playlist"])

            # Wait for show to end (poll FPP, with a generous timeout as safety net)
            max_wait = show_def.get("duration_mins", 20) * 60 * 3
            waited   = 0
            while waited < max_wait:
                time.sleep(5)
                waited += 5
                if is_fpp_idle():
                    break

            log.info("--- Show ended: %s ---", show_def["name"])

        finally:
            self._in_show.clear()
            # Restore lighting
            trigger_dim_restore(an_cfg)
            # Resume background music
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

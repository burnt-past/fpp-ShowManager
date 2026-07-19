#!/usr/bin/env python3
"""
XR18 Bridge for Falcon Pi Player
Syncs FPP system volume with XR18 music channels via OSC (UDP port 10024).
Controls a separate announcement channel at a fixed independent level.

XR18 OSC protocol: send /xremote every 9s to subscribe; XR18 sends fader
updates back to whichever host last sent /xremote.
"""

import fcntl
import json
import logging
import logging.handlers
import os
import socket
import struct
import threading
import time
import urllib.error
import urllib.request

CONFIG_PATH        = "/home/fpp/media/config/ShowManagerHardware.config"
LEGACY_CONFIG_PATH = "/home/fpp/media/config/ShowManager.config"
LOG_PATH    = "/home/fpp/media/logs/xr18_bridge.log"
XR18_PORT   = 10024
LISTEN_PORT = 10023   # local UDP port; XR18 sends updates back here
FPP_VOL_URL = "http://localhost/api/system/volume"
POLL_INTERVAL   = 2   # seconds between FPP volume polls
XREMOTE_INTERVAL = 9  # seconds between /xremote heartbeats
ANNOUNCE_SYNC_INTERVAL = 30  # seconds between announcement-channel restorations
PAUSE_SYNC_FLAG  = "/tmp/xr18_pause_sync"    # scheduler creates this during announcements
FADER_STATE_FILE = "/tmp/xr18_current_fader" # bridge writes current level here

# Rotate at ~2 MB, keep 3 old copies — bounded disk use, no external cron.
_handler = logging.handlers.RotatingFileHandler(
    LOG_PATH, maxBytes=2_000_000, backupCount=3
)
_handler.setFormatter(logging.Formatter("%(asctime)s %(levelname)s %(message)s"))
logging.basicConfig(level=logging.INFO, handlers=[_handler])
log = logging.getLogger(__name__)


# ---------------------------------------------------------------------------
# Minimal OSC encode / decode (no external deps)
# ---------------------------------------------------------------------------

def _pad4(b: bytes) -> bytes:
    rem = len(b) % 4
    return b + b"\x00" * ((4 - rem) % 4)

def _osc_str(s: str) -> bytes:
    return _pad4((s + "\x00").encode())

def build_osc(address: str, *args) -> bytes:
    """Build an OSC message with optional float/int/str arguments."""
    type_tag = ","
    arg_bytes = b""
    for a in args:
        if isinstance(a, float):
            type_tag += "f"
            arg_bytes += struct.pack(">f", a)
        elif isinstance(a, int):
            type_tag += "i"
            arg_bytes += struct.pack(">i", a)
        elif isinstance(a, str):
            type_tag += "s"
            arg_bytes += _osc_str(a)
    return _osc_str(address) + _osc_str(type_tag) + arg_bytes

def parse_osc(data: bytes):
    """Parse an OSC packet; returns (address, [args]) or None on error."""
    try:
        addr_end = data.index(b"\x00")
        address  = data[:addr_end].decode()
        offset   = ((addr_end + 4) // 4) * 4

        tag_end  = data.index(b"\x00", offset)
        type_tag = data[offset:tag_end].decode()
        offset   = ((tag_end + 4) // 4) * 4

        args = []
        for ch in type_tag:
            if ch == ",":
                continue
            if ch == "f":
                args.append(struct.unpack(">f", data[offset:offset+4])[0])
                offset += 4
            elif ch == "i":
                args.append(struct.unpack(">i", data[offset:offset+4])[0])
                offset += 4
            elif ch == "s":
                s_end = data.index(b"\x00", offset)
                args.append(data[offset:s_end].decode())
                offset = ((s_end + 4) // 4) * 4
        return address, args
    except Exception:
        return None


# ---------------------------------------------------------------------------
# FPP volume API helpers
# ---------------------------------------------------------------------------

def fpp_get_volume() -> int | None:
    try:
        with urllib.request.urlopen(FPP_VOL_URL, timeout=3) as r:
            return int(json.loads(r.read()).get("volume", 0))
    except Exception as e:
        log.warning("FPP get-volume failed: %s", e)
        return None

def fpp_set_volume(vol: int):
    vol = max(0, min(100, vol))
    try:
        req = urllib.request.Request(
            FPP_VOL_URL,
            data=json.dumps({"volume": vol}).encode(),
            headers={"Content-Type": "application/json"},
            method="PUT",
        )
        urllib.request.urlopen(req, timeout=3)
    except Exception as e:
        log.warning("FPP set-volume failed: %s", e)


# ---------------------------------------------------------------------------
# Main bridge
# ---------------------------------------------------------------------------

def ch_fader_addr(ch: str) -> str:
    return f"/ch/{ch.zfill(2)}/mix/fader"

class XR18Bridge:
    def __init__(self, cfg: dict):
        # New UI keys (mixer_ip / fader_channel) win; legacy keys are fallback.
        self.xr18_ip = cfg.get("mixer_ip") or cfg.get("xr18_ip") or "192.168.0.1"
        fch = cfg.get("fader_channel")
        if fch is not None:
            self.music_ch1 = self.music_ch2 = str(int(fch))
        else:
            self.music_ch1 = str(cfg.get("music_ch1", "01"))
            self.music_ch2 = str(cfg.get("music_ch2", "02"))
        self.announce_ch = str(cfg.get("announce_ch", "03"))
        self.announce_vol = float(cfg.get("announce_vol", "0.75"))

        self._music_addrs = {
            ch_fader_addr(self.music_ch1),
            ch_fader_addr(self.music_ch2),
        }
        self._prev_fpp_vol: int | None = None
        self._suppress_fpp_update = False   # avoid round-trip echo
        self._lock = threading.Lock()

        # One shared socket so /xremote and the listener use the same source port
        self._sock = socket.socket(socket.AF_INET, socket.SOCK_DGRAM)
        self._sock.setsockopt(socket.SOL_SOCKET, socket.SO_REUSEADDR, 1)
        self._sock.bind(("", LISTEN_PORT))
        self._sock.settimeout(1.0)

    # --- OSC send -----------------------------------------------------------

    def _send(self, address: str, *args):
        self._sock.sendto(build_osc(address, *args), (self.xr18_ip, XR18_PORT))

    # --- Threads ------------------------------------------------------------

    def _xremote_heartbeat(self):
        """Keep the XR18 sending us fader updates."""
        while True:
            try:
                self._send("/xremote")
            except Exception as e:
                log.warning("xremote heartbeat error: %s", e)
            time.sleep(XREMOTE_INTERVAL)

    def _poll_fpp(self):
        """FPP → XR18: push volume changes to music channels."""
        while True:
            # Yield control to the scheduler during announcements
            if os.path.exists(PAUSE_SYNC_FLAG):
                time.sleep(POLL_INTERVAL)
                continue

            vol = fpp_get_volume()
            if vol is not None:
                with self._lock:
                    if vol != self._prev_fpp_vol and not self._suppress_fpp_update:
                        fader = vol / 100.0
                        self._send(ch_fader_addr(self.music_ch1), fader)
                        self._send(ch_fader_addr(self.music_ch2), fader)
                        log.info(
                            "FPP vol %d → XR18 ch%s/ch%s fader %.3f",
                            vol, self.music_ch1, self.music_ch2, fader,
                        )
                        self._prev_fpp_vol = vol
                        # Share current fader level with the scheduler
                        try:
                            with open(FADER_STATE_FILE, "w") as f:
                                f.write(f"{fader:.4f}")
                        except Exception:
                            pass
            time.sleep(POLL_INTERVAL)

    def _listen_xr18(self):
        """XR18 → FPP: translate music-channel fader moves to FPP volume."""
        log.info("OSC listener on UDP %d", LISTEN_PORT)
        while True:
            try:
                data, _ = self._sock.recvfrom(4096)
            except socket.timeout:
                continue
            except Exception as e:
                log.error("OSC recv error: %s", e)
                continue

            result = parse_osc(data)
            if result is None:
                continue
            address, args = result
            if address not in self._music_addrs or not args:
                continue

            fader = float(args[0])
            vol   = round(fader * 100)
            with self._lock:
                if vol != self._prev_fpp_vol:
                    self._suppress_fpp_update = True
                    fpp_set_volume(vol)
                    self._prev_fpp_vol = vol
                    self._suppress_fpp_update = False
                    log.info(
                        "XR18 ch fader %.3f → FPP vol %d", fader, vol
                    )

    def _maintain_announce(self):
        """Periodically restore the announcement channel to its configured level.

        This prevents the announcement channel from being left silent if the
        XR18 is power-cycled or reset between shows.
        """
        while True:
            try:
                self._send(ch_fader_addr(self.announce_ch), self.announce_vol)
            except Exception as e:
                log.warning("Announce channel update error: %s", e)
            time.sleep(ANNOUNCE_SYNC_INTERVAL)

    # --- Entry point --------------------------------------------------------

    def run(self):
        log.info(
            "XR18 Bridge starting — XR18 %s, music ch%s+ch%s, announce ch%s @ %.0f%%",
            self.xr18_ip, self.music_ch1, self.music_ch2,
            self.announce_ch, self.announce_vol * 100,
        )

        # Set announcement channel immediately at startup
        try:
            self._send(ch_fader_addr(self.announce_ch), self.announce_vol)
        except Exception as e:
            log.warning("Initial announce channel set failed: %s", e)

        threads = [
            threading.Thread(target=self._xremote_heartbeat, daemon=True, name="xremote"),
            threading.Thread(target=self._poll_fpp,          daemon=True, name="poll-fpp"),
            threading.Thread(target=self._listen_xr18,       daemon=True, name="xr18-listener"),
            threading.Thread(target=self._maintain_announce, daemon=True, name="announce"),
        ]
        for t in threads:
            t.start()
        for t in threads:
            t.join()


def _load_cfg(path):
    try:
        with open(path) as f:
            return json.load(f)
    except FileNotFoundError:
        return {}
    except Exception as e:
        log.error("Config load error %s: %s", path, e)
        return {}


LOCK_PATH = "/tmp/showmanager_bridge.lock"

def _open_lock_file(path):
    """Open the lock file tolerantly. It may already exist owned by a different
    user (created by root at boot, reopened by the web user on a restart), so
    fall back to a read-only fd — which is still enough for flock(). On create,
    make it world-writable so any launcher can reuse it."""
    try:
        fd = os.open(path, os.O_RDWR | os.O_CREAT, 0o666)
        try:
            os.chmod(path, 0o666)
        except OSError:
            pass
        return os.fdopen(fd, "r+")
    except PermissionError:
        try:
            return open(path, "r")
        except OSError:
            return None

def acquire_single_instance():
    """Exclusive lock so only one bridge ever runs. Without this, a relaunch
    that races an already-running copy (e.g. a plugin update) leaves two
    bridges fighting over the XR18 faders. The lock releases when the holding
    process dies."""
    handle = _open_lock_file(LOCK_PATH)
    if handle is None:
        log.warning("Could not open lock file %s — starting without a lock", LOCK_PATH)
        return True
    try:
        fcntl.flock(handle, fcntl.LOCK_EX | fcntl.LOCK_NB)
    except OSError:
        log.warning("Another bridge is already running — exiting this copy")
        return None
    try:
        handle.seek(0)
        handle.truncate()
        handle.write(str(os.getpid()))
        handle.flush()
    except (OSError, ValueError):
        pass
    return handle   # keep the fd open for the process lifetime to hold the lock


if __name__ == "__main__":
    _lock = acquire_single_instance()
    if _lock is None:
        raise SystemExit(0)

    # Merge legacy config underneath the current one so old installs keep working
    cfg = {**_load_cfg(LEGACY_CONFIG_PATH), **_load_cfg(CONFIG_PATH)}
    if not cfg:
        log.warning("No config found at %s — using defaults", CONFIG_PATH)

    XR18Bridge(cfg).run()

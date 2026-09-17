#!/usr/bin/env bash
# Provision a Debian 13 VM into an FPP 10 box with fpp-ShowManager installed.
# Idempotent: the slow FPP compile only runs once.
set -euo pipefail

FPP_BRANCH="${FPP_BRANCH:-v10.1}"
SRC="/srv/fpp-showmanager-src"                       # this plugin checkout (synced)
PLUGIN_DIR="/home/fpp/media/plugins/fpp-ShowManager"
log() { echo -e "\n=== $* ===\n"; }

# --- 1. FPP 10 (only if not already installed) -------------------------------
if [ ! -d /opt/fpp ]; then
  log "Installing FPP ${FPP_BRANCH} — this compiles fppd and takes 20-40 min"
  export DEBIAN_FRONTEND=noninteractive
  apt-get update -qq
  apt-get install -yq git ca-certificates
  git clone --depth 1 --branch "${FPP_BRANCH}" https://github.com/FalconChristmas/fpp /opt/fpp
  # Desktop mode (auto-detected on a generic VM) skips host network reconfig.
  yes | bash /opt/fpp/SD/FPP_Install.sh --branch "${FPP_BRANCH}"
else
  log "FPP already installed at /opt/fpp — skipping compile"
fi

# --- 2. Make sure the fpp user + media tree exist ----------------------------
id fpp >/dev/null 2>&1 || useradd -m -d /home/fpp fpp
mkdir -p /home/fpp/media/plugins /home/fpp/media/config /home/fpp/media/logs

# --- 3. Symlink THIS plugin checkout into FPP's plugin dir --------------------
# A symlink keeps your local edits live in the VM.
if [ ! -e "$PLUGIN_DIR" ]; then
  ln -s "$SRC" "$PLUGIN_DIR"
  log "Linked $SRC -> $PLUGIN_DIR"
fi
chown -h fpp:fpp "$PLUGIN_DIR" 2>/dev/null || true

# --- 4. Announcement playback + the optional brightness-fade plugin ----------
apt-get install -yq ffmpeg mpg123 || true
if [ ! -d /home/fpp/media/plugins/fpp-brightness ]; then
  git clone --depth 1 https://github.com/FalconChristmas/fpp-brightness \
    /home/fpp/media/plugins/fpp-brightness || echo "(fpp-brightness clone skipped)"
fi
chown -R fpp:fpp /home/fpp/media/plugins

# --- 5. Bring services up -----------------------------------------------------
log "Starting FPP services"
systemctl enable --now apache2 2>/dev/null || true
systemctl enable --now fppd 2>/dev/null || systemctl restart fppd 2>/dev/null || true
# Start our daemons now; on subsequent boots FPP's postStart hook launches them.
sudo -u fpp bash "$PLUGIN_DIR/scripts/restart_daemons.sh" 2>/dev/null || \
  bash "$PLUGIN_DIR/scripts/restart_daemons.sh" 2>/dev/null || true

log "Done. Open http://localhost:8080/  (Status/Control -> Plugins -> Show Manager)"
echo "If the UI 500s or fppd isn't up yet, run:  vagrant reload"

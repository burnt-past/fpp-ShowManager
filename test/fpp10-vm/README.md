# FPP 10 test VM

A throwaway **Debian 13 + FPP 10** virtual machine with this plugin already
installed, so you can smoke-test fpp-ShowManager against FPP 10 before touching
the real show controller. Debian 13 is FPP 10's clean x86 target (Ubuntu 24.04
is intentionally not used — its repos lack `libsdl3-dev`, which FPP 10's
installer needs).

## Prerequisites

- [Vagrant](https://developer.hashicorp.com/vagrant/install)
- [VirtualBox](https://www.virtualbox.org/) (or libvirt/KVM on Linux)
- ~6 GB free disk, and 20–40 min for the first boot (it compiles fppd)

## Run it

```bash
cd test/fpp10-vm
vagrant up                       # first boot compiles FPP 10 — be patient
```

Then open **http://localhost:8080/** → **Status/Control → Plugins → Show
Manager**. (Pin to a different FPP version with `FPP_BRANCH=v10.0 vagrant up`.)

The dedicated schedule-feed port is forwarded too, so once you enable it on the
Website Link card you can hit **http://localhost:8088/?key=...** from your host.

## What to verify (the FPP-10 checklist)

These are the things that could differ on FPP 10 — the plugin is otherwise
confirmed compatible from source:

- [ ] Plugin **pages load** (Status / Schedule / Background / Announcements / Hardware / System) with no PHP error
- [ ] **Both daemons run** — System tab shows Scheduler *Active*; `vagrant ssh -c 'pgrep -af "show_scheduler|xr18_bridge"'`
- [ ] **BG Effect status** reads correctly — start an effect in FPP and confirm the Status tab shows it (this exercises the FPP-10 `runningEffects` fix)
- [ ] **Feed** returns JSON — enable the dedicated port + a key, then open `http://localhost:8088/?key=<key>`; wrong/no key → 403
- [ ] **Schedule → feed** round-trips: add a show, confirm it appears in the feed with the right Pacific offset
- [ ] Manual **Run Show / Start / Stop** work against a test playlist

## What this VM canNOT test (needs real hardware)

- **PipeWire audio** ↔ the onboard sound card and the **XR18** (announcements to
  the mixer line-in; show audio over USB). A VM has no XR18 and emulated audio,
  so the announce-device names under PipeWire and the OSC bridge behavior must be
  validated on a spare physical box. This is the real upgrade risk.

## Iterating

The repo is mounted at `/srv/fpp-showmanager-src` and symlinked into the plugin
dir, so edits on your machine are live in the VM. After changing daemon code:

```bash
vagrant ssh -c 'sudo -u fpp /home/fpp/media/plugins/fpp-ShowManager/scripts/restart_daemons.sh'
```

PHP/UI edits need no restart — just reload the page.

## Teardown

```bash
vagrant destroy -f      # delete the VM entirely
```

## Faster alternative (not a full VM)

FPP publishes an official container image. It's the quickest path to a running
FPP 10 (no compile), though audio/output support is limited and it's
"experts only" per the project:

```bash
docker run -it --rm -p 8080:80 \
  -v "$PWD/../..":/home/fpp/media/plugins/fpp-ShowManager \
  falconchristmas/fpp:latest
```

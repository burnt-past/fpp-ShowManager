# ShowManager — Design Package

A handoff brief for redesigning the UI of **ShowManager**, a Falcon Player (FPP) plugin that runs scheduled outdoor light-and-music shows. Everything a designer needs to understand the product, its constraints, and what to deliver is in this document. The source of truth for current markup/styles is `app.php` in this repo.

---

## 1. What the product is

ShowManager runs a seasonal outdoor show installation on a Raspberry Pi:

- **Schedules shows** (FPP playlists) on a calendar — one-offs, repeating rules (e.g. "every Fri/Sat at 7pm, every 30 min until 10pm"), and blackouts (whole day or a time range).
- **Controls a Behringer XR18 digital mixer** over OSC — syncs FPP volume with mixer faders, ducks music for announcements, sets show/idle volume levels.
- **Plays announcements** — pre-show ("show starts in 5 minutes"), and ambient daytime announcements on an interval.
- **Dims lighting** before shows via a brightness plugin.

One operator (the owner) uses it day-to-day from a laptop or phone. A future **kiosk mode** will let non-technical helpers start/stop shows from a wall-mounted tablet.

## 2. Hard constraints (non-negotiable)

- **Runs embedded inside the FPP web UI** as a plugin page. We control a `<div id="sm">` and everything inside it — not `<html>`, `<head>`, or the page chrome around it. Styles must be scoped (current CSS prefixes everything with `#sm` / `.sm-*` / `.cal-*`).
- **Single file, no build step.** The whole UI is one PHP file (`app.php`) with inline `<style>` and `<script>`. Vanilla JS only — no React, no npm, no bundler.
- **Fully offline.** The Pi often runs on an isolated show network with no internet. No CDN fonts, no external assets of any kind. System font stack only (or inline data-URI assets if truly needed).
- **Renders via `innerHTML` templates** with polling-based updates. Dynamic regions are patched in place on a timer (see §6). Designs should assume server-rendered-style HTML, not component state.
- **Targets**: desktop browser primary; must remain usable on a phone (operator checks status from the yard). Kiosk target is a landscape tablet.
- **Dark theme is the default**; a light theme exists via a toggle. Both must be first-class.

## 3. Current design tokens

Defined as CSS custom properties on `:root` (dark) and `.sm-light` (light override):

| Token | Dark | Light | Role |
|---|---|---|---|
| `--bg` | `#0f1117` | `#f0f2f7` | page background |
| `--base` | `#13161f` | `#e8ebf2` | tab bar background |
| `--card` | `#1a1d27` | `#ffffff` | card surface |
| `--raise` | `#1f2330` | `#f5f6fa` | raised surface / inputs |
| `--high` | `#252839` | `#eaecf4` | highest surface (log wells) |
| `--border` | `rgba(255,255,255,.07)` | `rgba(0,0,0,.08)` | hairlines |
| `--brdHi` | `rgba(255,255,255,.13)` | `rgba(0,0,0,.15)` | modal borders |
| `--text` | `#e2e6f3` | `#1a1d2e` | primary text |
| `--sub` | `#7c85a2` | `#4a5270` | secondary text |
| `--mut` | `#3e4558` | `#9098b8` | muted/labels |
| `--mint` | `#34d399` | `#059669` | primary accent (success, "next", CTAs) |
| `--amber` | `#f59e0b` | `#d97706` | live/"on air" state |
| `--red` | `#f43f5e` | `#dc2626` | danger, blackouts |
| `--s1` | `#3b82f6` | `#2563eb` | rule-generated entries |
| `--s2` | `#a855f7` | `#7c3aed` | (reserved) |

Type: `system-ui, sans-serif`; monospace for times, values, logs. Radii: 6–14px. Cards have soft shadows; the hero card gets a mint glow ring.

## 4. Information architecture

```
Now-playing strip (persistent, above tabs)  — "● On Air — <playlist>" or "Idle"
Tabs: Status | Schedule | Announcements | Hardware      (+ theme toggle)
```

### Status tab (home)
1. **Hero card** — On Air / Idle badge (amber pulse when live), current playlist name (32px), uptime, FPP volume %, XR18 fader value.
2. **Stat card row** — FPP Version, Instance (hostname), Shows Today, Upcoming, Volume, XR18 fader. Six small cards, flex-wrapped.
3. **Manual Trigger card** — playlist `<select>` + **▶ Start** + **⏸ Stop** buttons.
4. **Today's Schedule card** — time-ordered list; past shows dimmed; green **Next** badge; amber **Now** badge; full-blackout days show a red banner.
5. **System card** — three status rows with green/red dots (FPP Daemon, XR18, Scheduler) + Restart Scheduler button.
6. **Scheduler Log card** — monospace log well (auto-scrolls, refreshes every 3s) with Pause/Copy/Clear/Refresh buttons, plus a separate persistent **Manual / Probe Output** well (mint text) with its own Copy/Clear.

### Schedule tab
- Toolbar: Month/Week/Day segmented control, ‹ › nav, title, Today, **+ Add Show**, **Blackout Day**.
- **Month grid**: day cells with up to 3 chips (`19:00 PlaylistName`); mint chips = one-off shows, blue chips = rule-generated, red chips = timed blackouts; whole-day blackouts paint the cell red; today's cell highlighted.
- **Week view**: 7 columns, same chips. **Day view**: entry rows with edit/delete.
- **Repeating Rules panel** below the calendar: rule rows (playlist, days, window, interval, date range) with Edit/Delete, + New Rule.
- **Modals**: Day modal (entries + "+ Add Show / + Blackout / Day View"), Add Show (date/time/playlist), Blackout (date + optional start/end time, "leave blank to block whole day"), Rule form (date range, day-of-week toggle buttons, show time, repeat-until, interval, playlist).

### Announcements tab
Stacked cards: **Ducking** (duck level, fade secs, gain dB, max duration), **Lighting** (pre-show/normal brightness), **Background Music** (playlist select), **Pre-Show Announcements** (table: mins-before + audio file rows, add/remove), **Daytime Announcements** (enable, time window, interval), **Upload** (file + destination), **File list** (name, folder, delete).

### Hardware tab
One card: Mixer IP, Music fader channel, Show level, Idle level, Announce channel, Announce level, Save (restarts the bridge automatically).

## 5. States that matter

- **Idle vs On Air** — the single most important glanceable state. Amber + pulsing dot when live.
- **Next / Now / Past** in today's schedule.
- **Blackout** — whole-day (red banner / red calendar cell) vs timed (red chip with time range).
- **Daemon health** — scheduler or bridge stopped = red dot; this is an "act now" signal.
- **Empty states** — no shows today, no rules, no announcement files, empty log.
- **Errors** — currently browser `alert()` and inline log text only (see §7).

## 6. Update model (affects design)

- Now-playing strip: polls every 5s, on every tab.
- Status tab regions (`#sm-hero`, `#sm-stats`, `#sm-sched`, `#sm-sys`, log well): patched in place every 3s. **The Manual Trigger card and the Manual/Probe Output well are never re-rendered** — form selections and output must survive refreshes. Any redesign must preserve this "stable islands vs live regions" split or specify equivalent behavior.
- Schedule data is cached per month and re-fetched after edits.

## 7. Known UX debt (opportunities)

- Feedback is `alert()` and `confirm()` dialogs — needs a proper toast/confirm pattern.
- The six stat cards are equal-weight; Volume/XR18 duplicate the hero. Hierarchy could be much better.
- Log wells are developer-grade; fine for the owner, but could be visually contained/collapsible.
- No loading states anywhere (first paint shows nothing until fetches return).
- Day-of-week toggles, chips, and badges are each styled ad hoc — a small component system would help.
- Mobile: stat cards and calendar get cramped; month grid has no responsive strategy beyond horizontal scroll.

## 8. Kiosk mode (design this too — it's next)

A full-screen, wall-tablet view for non-technical helpers:

- Giant now-playing state ("ON AIR — Main Street Magic" / "Next show 7:30 PM").
- One-tap **Start show** (from a short allowed list) and **Stop** with a hold-to-confirm or two-step confirm — misfires are costly at a live venue.
- **"Disable system until…"** control (tonight / 1 hour / until re-enabled) for weather or emergencies.
- No access to schedule editing, hardware, or logs. High contrast, readable from ~2 m, dark, finger-first hit targets (≥ 48px).

## 9. Deliverables requested

1. A refreshed visual system (tokens, type scale, spacing, component styles: cards, buttons, badges, chips, inputs, modals, toasts) as **plain CSS on the existing custom-property architecture** — keep the `--token` names or supply a mapping.
2. Redesigned layouts for the four tabs, desktop and ~390px mobile.
3. The kiosk screen (landscape tablet).
4. Delivery format: static HTML/CSS mockups (self-contained, no external assets) are ideal — they can be lifted directly into `app.php`. Annotated images are acceptable for exploration rounds.

**Do not** introduce: web fonts/CDNs, a JS framework, a build step, or global (unscoped) styles.

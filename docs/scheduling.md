# Scheduling Guide

Shows are FPP **playlists** placed on a calendar by date. Each day can hold several shows, one-off or generated from repeating rules, plus blackouts. There is no separate "shows" definition step — you pick a playlist directly when you schedule it.

Open **Show Manager → Schedule**. Switch between **Month**, **Week**, and **Day** views with the segmented control.

---

## Adding a one-off show

1. Click a day (or **+ Add Show**).
2. Set the **date**, **time**, and pick an FPP **playlist**.
3. Save — it appears on the calendar as a green chip.

Click a day to reopen it and remove or edit entries.

---

## Repeating rules

For shows that recur, use **+ New Rule** instead of adding each night by hand:

| Field | Meaning |
|---|---|
| Playlist | The FPP playlist to run |
| Days | Which days of the week |
| Show time | First show of the night |
| Repeat until | (optional) end of the show window |
| Interval | (optional) minutes between shows across the window |
| Start / End date | The date range the rule is active |

Example: Fri & Sat, 7:00 PM to 10:00 PM, every 30 minutes, Nov 28 – Dec 31. The rule is expanded into individual show times on the calendar (blue "rule-generated" chips). Edit or delete the rule from the **Repeating Rules** panel below the calendar.

---

## Playlist rotation

Give a show entry (or rule) a **list** of playlists and it rotates — a different one each time that slot fires, so shows don't repeat back-to-back. State lives in `ShowManagerRotation.config`; delete its key to reset to the first playlist.

---

## Blackouts = quiet hours

Mark a day (or a time range) as a blackout when the show should not run:

- **Whole-day blackout** — no times set. The day is shown solid red; no shows run.
- **Timed blackout** — a start/end time. Only shows inside that window are suppressed; the day shows a red time-range chip.

A blackout is treated as the venue's **quiet hours**. During it:

| | Blackout |
|---|---|
| Shows | suppressed |
| Background **music** (audio) | suppressed |
| Background **effect** (lighting) | **keeps running** |

So the display stays lit during quiet hours, but the sound goes off. (The kiosk's **Disable system** is different — it turns *everything* off.)

---

## Background windows on the calendar

The daily background **music** and **effect** windows (set on the Background tab) are shown on every calendar day — a purple "Music HH:MM–HH:MM" chip and a cyan "Effect HH:MM–HH:MM" chip, pinned below the show chips. Day view lists them as rows with an Edit link.

---

## How the scheduler fires events

The schedule loop runs every **30 seconds**. For each of today's shows it computes the time remaining and fires events within a ±30-second window:

| Event | Fires |
|---|---|
| Pre-show announcement (`mins_before`) | ~that many minutes before show time |
| Pre-show brightness fade | at its lead time before the show (see below) |
| Show start | at show time |

Each event fires **at most once per day per slot** — a de-duplication set is cleared at midnight, and a single-instance lock prevents a second scheduler from double-firing.

**Around a show:**

1. The brightness fade eases lights down to the pre-show level through the pre-show window.
2. At show time the background overlay effect is stopped, then brightness snaps to normal.
3. The show playlist starts; music faders move to the show level.
4. The scheduler waits for that **playlist by name** to stop (not a fixed timer), with a 2-hour safety cap.
5. When the show ends, faders return to idle and background resumes per its schedule (unless a manual Stop is in effect, which stays quiet until the next show); the lights snap to 0 and fade back to normal over the post-show fade time.

---

## Making changes mid-day

Schedule edits take effect within 30 seconds — no restart. Caveats: an event whose window already passed today won't re-fire, and a running show isn't interrupted by schedule edits (use the Status/kiosk **Stop** for that).

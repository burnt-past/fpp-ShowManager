# Scheduling Guide

The scheduler lets you plan shows by calendar date rather than by day-of-week pattern. Each date can have multiple shows at different times, a rotation between playlists, or a full blackout.

---

## The Shows Page

Before you can schedule anything you need at least one show defined. Go to **XR18 Volume Control — Shows**.

```
┌─────────────────────────────────────────────────────────────────────────────┐
│ Show Definitions                                                             │
│─────────────────────────────────────────────────────────────────────────────│
│ ID          Name       FPP Playlist    Transition Playlist  Trans.  Duration │
│─────────────────────────────────────────────────────────────────────────────│
│ show_a    │ Show A   │ ShowPlaylistA │ PreShowFadeA       │  120s │  15 min │
│ show_b    │ Show B   │ ShowPlaylistB │ (none)             │    0s │  12 min │
│─────────────────────────────────────────────────────────────────────────────│
│ [+ Add Show]                                               [Save Shows]     │
└─────────────────────────────────────────────────────────────────────────────┘
```

### Fields explained

**ID** — A short code with no spaces (e.g. `show_a`). Used internally in the schedule data. Pick something you'll recognize.

**FPP Playlist** — The playlist FPP plays for the main show. Populated from your actual FPP playlists.

**Transition Playlist** — Optional. An FPP sequence you've built that fades or dims the lights leading into the show. The scheduler triggers this first, waits `transition_secs`, then starts the main playlist. Leave blank if you don't need a transition.

**Transition Duration** — How long (in seconds) to run the transition before the main show starts. Should match the length of your transition sequence.

**Approximate Duration** — Your show's rough runtime in minutes. This is used as a safety timeout (×3 the value) in case FPP doesn't return to idle cleanly. **Always set this to your actual show length**, not a padded value — the scheduler detects the real end by polling FPP.

---

## The Schedule Calendar

Go to **XR18 Volume Control — Schedule**.

```
┌─────────────────────────────────────────────────────────────────────────────┐
│  ‹ Prev          December 2024          Next ›                               │
│─────────────────────────────────────────────────────────────────────────────│
│  Sun      Mon      Tue      Wed      Thu      Fri      Sat                  │
│─────────────────────────────────────────────────────────────────────────────│
│  1        2        3        4        5        6        7                    │
│                                      19:00    19:00    19:30                │
│                                      Show A   Show A   ↻ A/B               │
│─────────────────────────────────────────────────────────────────────────────│
│  8        9        10       11       12       13       14                   │
│                             ⛔ BLKOUT          19:00    19:30               │
│                                               Show B   ↻ A/B               │
│─────────────────────────────────────────────────────────────────────────────│
│  ...                                                                        │
└─────────────────────────────────────────────────────────────────────────────┘
```

- **Green badges** — scheduled shows with their start times
- **↻ A/B** — rotation slot (alternates between Show A and Show B)
- **Red background + ⛔ BLKOUT** — blackout date, no shows will fire

---

## Adding a show to a day

1. Click on the date
2. The day editor slides open:

```
┌─────────────────────────────────────┐
│ Saturday, December 7 2024           │
│─────────────────────────────────────│
│ 19:30 — Show A                  [✕] │
│ 21:00 — ↻ Show A / Show B       [✕] │
│                                     │
│ ─── Add Show ───                    │
│ Time: [19:00]                       │
│ Assignment:                         │
│   ● Specific show  ○ Rotation       │
│ Show: [Show A ▾]                   │
│                                     │
│ [Add Show to Day]                   │
│─────────────────────────────────────│
│ [Mark as Blackout]   [Close]        │
└─────────────────────────────────────┘
```

3. Set the **time** (24-hour or 12-hour depending on your browser locale)
4. Choose **Specific show** and pick from the dropdown, or choose **Rotation** and tick two or more shows
5. Click **Add Show to Day** — it appears on the calendar immediately
6. Click **✕** next to any entry to remove it

---

## Rotation — alternating shows automatically

When you pick **Rotation** and select two or more shows, the scheduler automatically cycles through them in order each time that slot fires:

```
Dec 7  21:00  → Show A   (index 0)
Dec 8  21:00  → Show B   (index 1)
Dec 9  21:00  → Show A   (index 0, wraps)
...
```

The state is stored in `XR18RotationState.config`. If you want to reset the rotation (start back at Show A), delete the file or use the FPP SSH terminal:

```bash
rm /home/fpp/media/config/XR18RotationState.config
```

Rotation is per slot key (the comma-joined list of show IDs). Changing which shows are in the rotation creates a new key and resets the counter for that group.

---

## Blackout dates

Clicking **Mark as Blackout** on a date adds a blackout entry. On blackout dates:

- No shows fire, even if show entries exist for that date
- All pre-show announcements are suppressed
- Daytime announcements still play normally (they are not show-related)

To remove a blackout, open the day and click **Remove Blackout**.

---

## How the scheduler fires events

The scheduler loop runs every **30 seconds**. For each upcoming show on today's schedule it calculates how many minutes until show time. Events fire when within a ±30-second window of their target time:

| Event | Fires when |
|---|---|
| Pre-show announcement (e.g. 15 min) | 14:30–15:30 before show time |
| Pre-show announcement (e.g. 5 min) | 4:30–5:30 before show time |
| Show start | 0:30 before to 0:30 after show time |

Each event fires **at most once per day per slot** — a duplicate-prevention set is cleared at midnight. This means if the Pi clock drifts slightly or the daemon restarts mid-day, events won't double-fire.

---

## Making changes mid-day

Schedule changes take effect within 30 seconds with no restart required. However:

- If an event's window has already passed for today, it won't re-fire
- A show that is currently running is not interrupted by schedule changes
- Adding a show for a time that's only minutes away will work if the scheduler hasn't already passed the window

---

## Pre-show sequence timeline

Given a show at **19:30** with announcements at 15, 10, and 5 minutes, and a 2-minute transition:

```
19:13:30  → "Show starts in 15 minutes" announcement plays
            (music ducks, announcement plays, music restores)

19:18:30  → "Show starts in 10 minutes" announcement

19:23:30  → "Show starts in 5 minutes" announcement

19:28:00  → fpp-brightness dims to pre_show_brightness level
            Pre-show transition playlist triggers in FPP
            (your transition sequence runs for transition_secs)

19:30:00  → Main show playlist triggers in FPP
            Scheduler polls FPP status every 5 seconds

~19:45:00 → FPP returns to idle (show ended)
            fpp-brightness restores to normal_brightness
            Background music playlist starts (looping)

19:50:00  → (Daytime announcements resume if within the daytime window)
```

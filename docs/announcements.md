# Announcements, Ducking & Lighting

The **Announcements** tab covers three things that happen around shows: spoken announcements (with automatic music ducking), and the pre-show brightness fade. All settings save to `ShowManagerAnnouncements.config`.

There are two announcement modes — **pre-show** (timed to each show) and **daytime** (random, on an interval).

---

## Audio files

Upload from the tab (**Files** card → *Upload audio*), or drop files into:

```
/home/fpp/media/plugins/fpp-ShowManager/announcements/         ← pre-show
/home/fpp/media/plugins/fpp-ShowManager/announcements/daytime/ ← daytime
```

Pre-show rows can also point at audio already elsewhere in FPP — the file picker lists `.mp3/.wav/.ogg` in the plugin's folders **plus** anything in FPP's `music/` and `upload/` folders (offered as absolute paths). A saved file that no longer exists is shown as `(missing)` rather than silently dropped.

Recommended: MP3, 44.1/48 kHz, normalized to about −14 LUFS so the gain setting has a consistent reference. Keep pre-show clips under ~60 seconds.

---

## Ducking

When any announcement plays, the music ducks automatically — it never stops:

1. The bridge pauses fader sync (`/tmp/xr18_pause_sync`).
2. The current fader level is read from `/tmp/xr18_current_fader`.
3. Music faders fade from the current level down to `duck_level` over `duck_fade_secs`.
4. The clip plays through ALSA at `+gain_db` boost — clearly over the ducked music.
5. Faders fade back up; the pause flag is removed and sync resumes.

**Tuning:** start at `duck_level 0.25`, `gain_db 6`. Announcement too quiet → raise `gain_db` (9–12) or lower `duck_level` (0.15). Music too loud underneath → lower `duck_level` (0.10). Fade abrupt → raise `duck_fade_secs`.

| gain_db | ≈ loudness vs. ducked music |
|---|---|
| 6 | 2× — good default |
| 12 | 4× — noisy environments |
| 18 | 8× — very prominent |

Announcements are **downmixed to mono** (they're voice — mono keeps them centred and consistent).

---

## Announcements on their own mixer channel

By default announcements play out the Pi's **default** audio output — the same output FPP uses for music — and are simply mixed over the ducked music. If you'd rather they arrive on a **separate mixer channel** (their own fader, independent of the music), they need a **separate audio output device**.

> **Why a separate device (not another channel of the mixer's USB):** FPP opens the mixer's USB audio card **exclusively** while it's the audio output. A second program (the announcement player) can't independently open the *same* card at the same time — you'll get "device busy / host is down." So the reliable approach is to give announcements their **own** audio device.

The plugin needs a **second, independent output** so it never contends with FPP's card:

- **On an x86 box (mini-PC/server):** use the machine's **built-in audio** — no extra hardware. It's a separate ALSA card (e.g. `plughw:CARD=PCH,DEV=0`), untouched by FPP. Run its 3.5 mm output into a mixer line input.
- **On a Raspberry Pi** (no usable line-out): add a **cheap USB audio adapter** (~$10); it shows up as its own ALSA card.

Then:

**1. Wire that output into a physical line input** on the mixer — e.g. **input 3**.

**2. Point the plugin at it.** On the **Hardware** tab pick **Announcement audio device** = the onboard card / adapter (e.g. `plughw:CARD=PCH,DEV=0`) from the dropdown, and hit **Test tone** — you should see **channel 3** meter, music untouched.

**3. Set Announce channel = 3.** The plugin holds channel 3 at the **Announce level** over OSC and ducks the music (ch 1/2) underneath while a clip plays.

Announcements are emitted as **dual-mono** (the same mono content on both L and R of the output), so it doesn't matter which side of the 3.5 mm feeds the mixer input.

> Trying to route to a spare *USB channel of the mixer's own card* (e.g. XR18 USB 3/4) does **not** work while FPP is using that card — FPP opens it exclusively. Use a separate device as above.

*(On an XR18 you could instead route to a spare USB channel via an ALSA `route` device — but only if FPP is **not** using that same card as its output. Since FPP normally owns the whole card, the separate-adapter route above is the dependable option.)*

The `mpg123` fallback also honours the device and mono; `ffmpeg` is preferred (handles gain + downmix cleanly).

---

## Pre-show announcements

Add one row per warning: **minutes before show** + **audio file**. Have as many or as few as you like (e.g. just a 1-minute countdown). Each row fires once per show, within ±30 s of its target time. If a show is already running when one is due, it's skipped and logged.

---

## Daytime announcements

Random clips from the `daytime/` folder on an interval during a configured window. A daytime clip is **skipped** when any of these is true:

| Condition | Reason |
|---|---|
| Outside the configured window | Respects quiet hours |
| Within 20 minutes of a scheduled show | Keeps clear of the pre-show period |
| A show is running, or the system is disabled | Show safety |

`interval_mins` is the **minimum** gap between clips, measured from when the last one finished — you won't get a burst after a show ends.

---

## Pre-show brightness fade

Requires the [fpp-brightness](https://github.com/FalconChristmas/fpp-brightness) plugin (no-ops without it). Configured under the **Lighting** section:

- **Pre-show brightness** — the dim level (0–200) the fade eases down to.
- **Normal brightness** — the full level (usually 100) the show runs at.
- **Fade time (s)** — how long the fade down takes.
- **Fade start** — either *"Fade time before show"* (default) or *"when a selected pre-show audio begins"*.
- **Post-show fade back (s)** — how long the lights take to come back up after a show ends (0 = snap straight back).

**Behavior:** brightness eases from normal down to the pre-show level over the fade time, then **holds** dim until show time. When the show starts, the background overlay effect is stopped first, then brightness **snaps back to normal** — a smooth dim-down into a bright reveal.

The fade *starts* either the fade time before the show, or (if you pick a pre-show audio as the anchor) the moment that announcement plays — so you can sync the dim to a countdown clip. The fade *duration* is always your Fade time value.

**After the show:** when the playlist ends, the scheduled background is restored first, then brightness **snaps to 0 and fades back up to normal** over the post-show fade time. Leave it at 0 to snap straight back to normal (the original behavior). A new show starting during the fade takes over immediately.

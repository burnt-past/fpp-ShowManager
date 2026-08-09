# Website Integration

Show Manager can feed a **public website** with tonight's show times, a status
banner, and special-event cards. The website renders everything from one small
JSON document — the *contract* between the plugin and the site.

The feed carries **show times only** — no settings, no credentials, no anything
that could compromise the box. Everything site-specific (the destination URL,
the auth token, the allowed origin) is entered on the **System → Website Link**
card and stored in `ShowManagerPublish.config` (mode `0600`, excluded from
backups). Nothing is hardcoded in the plugin.

> **Security:** never expose the show controller to the internet. Both supported
> topologies keep it off the public net — the recommended one opens nothing
> inbound at all.

## The feed

Served read-only at:

```
plugin.php?plugin=fpp-ShowManager&page=ajax.php&nopage=1&action=public_schedule
```

Response headers: `Content-Type: application/json`,
`Cache-Control: public, max-age=60`, and
`Access-Control-Allow-Origin: <allow_origin>` (default `*`).

### Response shape

```json
{
  "status": "ok",
  "statusNote": "",
  "shows": [
    { "name": "Light & Snow Show", "start": "2026-12-05T18:00:00-08:00" },
    { "name": "Light & Snow Show", "start": "2026-12-05T18:30:00-08:00" }
  ],
  "events": [
    {
      "name": "Opening Night & Tree Lighting",
      "iso": "2026-11-27T18:00:00-08:00",
      "date": "Fri, Nov 27",
      "desc": "The plaza lights up for the first time this season."
    }
  ]
}
```

| Field | Required | Notes |
|---|---|---|
| `shows[].start` | yes | ISO-8601 **with an explicit UTC offset** (from the box's local timezone), so it reads correctly for any visitor. Drives the countdown, tonight's list, and the banner. |
| `shows[].name` | no | The playlist name; defaults to `"Light Show"`. |
| `status` | no | Anything other than `"ok"` flips the site to its paused banner. |
| `statusNote` | no | Shown verbatim when paused, e.g. "Shows paused for high winds." |
| `events[]` | no | Special-event cards. `iso` powers "Add to calendar"; omit it (use a `date` label like "All season") for open-ended entries. |

**Horizon:** the feed covers tonight through the next 7 days. Past shows are
dropped automatically, except a just-started one lingers briefly so the site can
still show it as live.

**Status** is `paused` when the operator ticks the pause box on the Website Link
card *or* while the system is disabled ("Disable system"); otherwise `ok`.

## Network topology

### A · Push (recommended)

The scheduler builds the feed and **uploads it to your static host** every few
minutes. Nothing inbound is ever opened, and a plaza-network outage leaves the
last-known schedule online. The **Publish now** button uses the same path.

On the Website Link card set: **Auto-publish on**, the **Upload URL** on your
host, the **method** it accepts (`PUT`/`POST`), and an **auth header/token** if
your host needs one. Requires outbound HTTPS from the box.

### B · Pull through a tunnel

Point a **Cloudflare Tunnel** (or similar) at the read-only feed URL above and
have the site fetch it directly. Truly live, but the site then depends on the
box being reachable. Use the **Copy** button on the card to grab the URL; leave
the upload settings blank.

## Special events

The card has a small editor for the `events[]` cards. Give each a name and a
short description, then either pick a date & time (becomes `iso` + a human
`date`) or type an open-ended label like "All season" (no `iso`, so the site
omits its "Add to calendar" link).

## Checklist

- [ ] Feed returns valid JSON with a `200` and the CORS header
- [ ] Every timestamp carries a UTC offset
- [ ] Tonight's times on the site match the controller exactly
- [ ] Setting the site to paused changes its banner within one refresh
- [ ] Stopping the feed degrades the site to placeholder times, not an error
- [ ] Verified on a phone over cellular, not just plaza wifi

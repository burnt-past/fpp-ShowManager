# Website Integration

Show Manager can feed a **public website** with tonight's show times and a
status banner. The website renders the countdown and tonight's list from one
small JSON document — the *contract* between the plugin and the site.

The feed carries **show times only** — no settings, no credentials, nothing that
could compromise the box. Everything site-specific (the feed key, the allowed
origin) is entered on the **System → Website Link** card and stored in
`ShowManagerPublish.config` (mode `0600`, excluded from backups). Nothing is
hardcoded in the plugin.

> **Security model:** the show controller never goes on the public internet.
> The website's own server-side proxy fetches the feed, so visitors never touch
> the box, and the feed URL carries a secret key that never reaches a browser.

## How it works (recommended)

1. The website has a **server-side proxy** (e.g. a Cloudflare Worker) that
   fetches your feed on an interval and caches it (~30s). Visitors hit the
   proxy, never your box.
2. You expose **only the feed path** to that proxy through a **Cloudflare
   Tunnel** (or similar) — the rest of the box stays private.
3. Because the fetch is server-to-server, **no CORS is needed**, and the secret
   **key travels on that fetch only** — it never appears in any visitor's
   browser.

### Set it up

1. On **System → Website Link**, click **Generate** to create a secret key,
   then **Save**.
2. Copy the **Public feed URL** shown there. It looks like:
   ```
   https://<your-tunnel-host>/plugin.php?plugin=fpp-ShowManager&page=ajax.php&nopage=1&action=public_schedule&key=<secret>
   ```
   (Point the tunnel's public hostname at `http://localhost:80`; the path and
   query stay the same, so just swap the host into the copied URL.)
3. In the site admin → **Schedule → Live schedule source**, paste that URL,
   **Save**, then **Test**.

Without the key (or with the wrong key) the endpoint returns **403** — a scanner
that stumbles onto the tunnel can't read the feed.

## The feed

Served read-only at:

```
plugin.php?plugin=fpp-ShowManager&page=ajax.php&nopage=1&action=public_schedule&key=<secret>
```

Headers: `Content-Type: application/json`, `Cache-Control: public, max-age=60`
(and `Access-Control-Allow-Origin` only if you set an allowed origin).

### Response shape

```json
{
  "status": "ok",
  "statusNote": "",
  "shows": [
    { "name": "Light & Snow Show", "start": "2026-12-05T18:00:00-08:00" },
    { "name": "Light & Snow Show", "start": "2026-12-05T18:30:00-08:00" }
  ]
}
```

| Field | Required | Notes |
|---|---|---|
| `shows[].start` | yes | ISO-8601 **with an explicit UTC offset** (from the box's local timezone), so it reads correctly for any visitor. |
| `shows[].name` | no | The playlist name; defaults to `"Light Show"`. |
| `status` | no | Anything other than `"ok"` shows the site's paused state. |
| `statusNote` | no | Shown when paused, e.g. "Shows paused for high winds." |
| `events[]` | no | Optional special-event cards (`name` + `desc`, plus a `when`→`iso`/`date` or a free-text `label`). Some sites render them; others ignore them. |

**Horizon:** the feed covers tonight through the next 7 days. Past shows are
dropped automatically, except a just-started one lingers briefly so the site can
still show it as live.

**Status** is `paused` when you tick the pause box on the Website Link card *or*
while the system is disabled ("Disable system"); otherwise `ok`.

## Alternative: push to a static host

If your site serves its own copy of the feed instead of pulling, the plugin can
**upload** it to a static host every few minutes (opens nothing inbound). On the
Website Link card, open **Alternative: push to a static host**, set the upload
URL, method, and any auth header, tick **Auto-publish**, and **Save**. **Publish
now** sends it immediately.

## Checklist

- [ ] A secret key is generated and saved
- [ ] Only the feed path is exposed (tunnel), not the whole box
- [ ] The feed URL returns valid JSON with a `200`; wrong/missing key returns `403`
- [ ] Every timestamp carries a UTC offset
- [ ] Tonight's times on the site match the controller exactly
- [ ] Setting the site to paused changes its banner within a minute
- [ ] Verified on a phone over cellular, not just plaza wifi

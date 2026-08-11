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
2. The plugin runs a **dedicated, localhost-only feed server** on its own port.
   Its only job is to return the schedule JSON — it can't reach FPP's admin.
3. A **Cloudflare Tunnel** points at *that port*, so the tunnel is physically
   limited to the feed. FPP's unauthenticated web UI is never exposed.
4. The fetch is server-to-server (**no CORS needed**) and the secret **key
   travels on that fetch only** — it never appears in a visitor's browser.

> **Do not** point the tunnel at `localhost:80`. That is FPP's web UI, which has
> no authentication — it would put the whole show controller (including
> disable/trigger actions) on the internet. Use the dedicated port below.

### Set it up

1. On **System → Website Link**:
   - Click **Generate** for a secret key.
   - Tick **Serve on a dedicated port for the tunnel** (default `8088`).
   - **Save**. (The feed server starts within ~15s; the scheduler daemon must be
     running.)
2. Install the tunnel on the FPP box (from the Cloudflare dashboard's
   *Create a Tunnel* screen — pick **Debian / 64-bit** and run the
   `cloudflared service install <token>` command it gives you).
3. In the tunnel's **Public Hostname** page, add a hostname (e.g.
   `schedule.yourdomain.com`) with **Service → HTTP → `localhost:8088`**
   (match the port above). Leave the path empty.
4. Your feed URL is then:
   ```
   https://schedule.yourdomain.com/?key=<secret>
   ```
   In the site admin → **Schedule → Live schedule source**, paste it, **Save**,
   then **Test**.

Without the key (or with the wrong key) the endpoint returns **403** — a scanner
that stumbles onto the tunnel can't read the feed. The **Open** button on the
card tests the local endpoint directly (reachable on your LAN) so you can
confirm the JSON before wiring the tunnel.

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

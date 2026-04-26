<?php
$scheduleFile = $settings['configDirectory'] . "/ShowManagerSchedule.config";
$schedule     = file_exists($scheduleFile)
    ? (json_decode(file_get_contents($scheduleFile), true) ?? [])
    : [];
$today        = date('Y-m-d');
$todayEntries = array_filter($schedule['entries'] ?? [], fn($e) => $e['date'] === $today);
$todayShows   = array_values(array_filter($todayEntries, fn($e) => $e['type'] === 'show'));
$isBlackout   = (bool) array_filter($todayEntries, fn($e) => $e['type'] === 'blackout');
usort($todayShows, fn($a, $b) => strcmp($a['time'], $b['time']));
?>
<style>
.stat-grid  { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 12px; }
@media (max-width: 500px) { .stat-grid { grid-template-columns: 1fr; } }
.stat-card  { background: #2a2a2a; border: 1px solid #484848; border-radius: 8px; padding: 14px 16px; }
.stat-lbl   { font-size: .72em; text-transform: uppercase; letter-spacing: .06em; color: #888; margin-bottom: 6px; }
.stat-val   { font-size: 1.25em; font-weight: 700; color: #eee; word-break: break-word; }
.stat-sub   { font-size: .82em; margin-top: 4px; }
.playing    { color: #4caf50; }
.idle       { color: #888; }
.vol-row    { display: flex; align-items: center; gap: 8px; margin-top: 8px; font-size: .85em; color: #bbb; }
.vol-label  { width: 36px; flex-shrink: 0; }
.vol-track  { flex: 1; height: 8px; background: #444; border-radius: 4px; overflow: hidden; }
.vol-fill   { height: 100%; background: #1a7fd4; border-radius: 4px; width: 0; transition: width .4s; }
.vol-pct    { width: 36px; text-align: right; flex-shrink: 0; }
.sched-item { display: flex; align-items: baseline; gap: 8px; padding: 6px 0;
              border-bottom: 1px solid #3a3a3a; font-size: .88em; color: #ccc; }
.sched-item:last-child { border-bottom: none; }
.sched-time { font-weight: 700; color: #4a9fd4; flex-shrink: 0; width: 48px; }
.sched-name { flex: 1; }
.sched-done { color: #555; text-decoration: line-through; }
.sched-next { color: #4caf50; font-size: .78em; }
.blackout-banner { background: #4a1515; border: 1px solid #7a2020; border-radius: 6px;
                   padding: 10px 14px; margin-bottom: 12px; color: #f88; font-size: .9em; }
</style>

<?php if ($isBlackout): ?>
<div class="blackout-banner">⛔ Today is a blackout day — no shows scheduled.</div>
<?php endif; ?>

<div class="stat-grid">
  <div class="stat-card">
    <div class="stat-lbl">Now Playing</div>
    <div class="stat-val" id="now-playing">—</div>
    <div class="stat-sub idle" id="play-status">Loading…</div>
  </div>
  <div class="stat-card">
    <div class="stat-lbl">FPP Volume</div>
    <div class="stat-val" id="fpp-vol-val">—</div>
    <div class="vol-row" style="margin-top:10px;">
      <div class="vol-track"><div class="vol-fill" id="fpp-vol-bar"></div></div>
    </div>
  </div>
</div>

<div class="stat-card" style="margin-bottom:12px;">
  <div class="stat-lbl">XR18 Music Fader</div>
  <div class="vol-row" style="margin-top:6px;">
    <div class="vol-label" id="xr18-vol-val">—</div>
    <div class="vol-track"><div class="vol-fill" id="xr18-vol-bar" style="background:#e67e22;"></div></div>
    <div class="vol-pct" id="xr18-vol-pct"></div>
  </div>
</div>

<div class="stat-card">
  <div class="stat-lbl">Today's Schedule<?= $isBlackout ? ' — BLACKOUT' : '' ?></div>
  <?php if (empty($todayShows)): ?>
  <p style="color:#666;font-size:.88em;margin:6px 0 0;">No shows scheduled today.</p>
  <?php else: ?>
  <div id="today-list">
  <?php
  $nowTime = date('H:i');
  foreach ($todayShows as $s):
      $past = $s['time'] < $nowTime;
      $name = $s['playlist'] ?? ('↻ ' . implode(' / ', $s['playlists'] ?? []));
  ?>
  <div class="sched-item">
    <span class="sched-time"><?= htmlspecialchars($s['time']) ?></span>
    <span class="sched-name <?= $past ? 'sched-done' : '' ?>"><?= htmlspecialchars($name) ?></span>
    <?php if (!$past): ?><span class="sched-next">upcoming</span><?php endif; ?>
  </div>
  <?php endforeach; ?>
  </div>
  <?php endif; ?>
</div>

<p style="font-size:.75em;color:#555;margin-top:10px;text-align:right;" id="last-refresh"></p>

<script>
const AJAX_BASE = '<?= "plugin.php?plugin=" . basename(__DIR__) . "&page=ajax.php&nopage=1" ?>';

async function refresh() {
    try {
        const [fpp, sm] = await Promise.all([
            fetch('/api/fppd/status').then(r => r.json()),
            fetch(`${AJAX_BASE}&action=get_status`).then(r => r.json()),
        ]);

        // Now playing
        const playlist = fpp.current_playlist?.playlist || null;
        const playing  = fpp.status === 1;
        document.getElementById('now-playing').textContent  = playlist || 'Idle';
        const statusEl = document.getElementById('play-status');
        statusEl.textContent  = playing ? '● Playing' : '○ Idle';
        statusEl.className    = 'stat-sub ' + (playing ? 'playing' : 'idle');

        // FPP volume
        const vol = fpp.volume ?? 0;
        document.getElementById('fpp-vol-val').textContent = vol + '%';
        document.getElementById('fpp-vol-bar').style.width = vol + '%';

        // XR18 fader
        if (sm.xr18_fader != null) {
            const pct = Math.round(sm.xr18_fader * 100);
            document.getElementById('xr18-vol-val').textContent = pct + '%';
            document.getElementById('xr18-vol-bar').style.width = pct + '%';
            document.getElementById('xr18-vol-pct').textContent = pct + '%';
        }

        document.getElementById('last-refresh').textContent =
            'Updated ' + new Date().toLocaleTimeString();
    } catch (e) {
        document.getElementById('play-status').textContent = 'Error fetching status';
    }
}

refresh();
setInterval(refresh, 5000);
</script>

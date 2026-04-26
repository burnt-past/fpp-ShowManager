<?php
$showsFile = $settings['configDirectory'] . "/ShowManagerShows.config";
$showsData = file_exists($showsFile) ? json_decode(file_get_contents($showsFile), true) : [];
$showDefs  = $showsData['shows'] ?? [];
?>
<style>
/* ── Calendar ─────────────────────────────── */
#cal-wrap   { overflow-x: auto; -webkit-overflow-scrolling: touch; }
#cal-grid   { table-layout: fixed; width: 100%; min-width: 280px; border-collapse: separate; border-spacing: 2px; }
#cal-grid th { padding: 5px 2px; text-align: center; font-size: .72em; font-weight: 600; color: #888; }
#cal-grid td { border: 1px solid #555; border-radius: 3px; padding: 5px 4px; vertical-align: top;
               cursor: pointer; min-height: 62px; background: #2d2d2d; color: #ddd; }
#cal-grid td:hover { background: #383838; }
#cal-grid td.is-today   { border: 2px solid #4a9fd4; }
#cal-grid td.is-blackout { background: #4a1515; }
#cal-grid td.is-empty   { background: transparent; border: none; cursor: default; }
.cal-num   { font-size: .82em; font-weight: 700; margin-bottom: 2px; }
.cal-show  { display: block; font-size: .62em; background: #1d6b35; color: #cef5d8;
             border-radius: 2px; padding: 1px 3px; margin-top: 2px;
             overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.cal-blk   { font-size: .62em; color: #f77; margin-top: 2px; }

/* ── Day modal ────────────────────────────── */
#day-modal  { display: none; position: fixed; inset: 0; background: rgba(0,0,0,.72);
              z-index: 9999; overflow-y: auto; padding: 16px 10px; }
.m-box      { background: #2d2d2d; color: #e8e8e8; border-radius: 8px;
              padding: 20px 16px; max-width: 460px; margin: 0 auto; }
.m-box h3   { margin: 0 0 12px; font-size: 1.05em; color: #fff; }
.m-box hr   { border-color: #484848; margin: 14px 0; }
.m-row      { margin-bottom: 10px; }
.m-row label { display: block; font-size: .88em; color: #bbb; margin-bottom: 3px; }
.m-row select, .m-row input { background: #3d3d3d; color: #eee; border: 1px solid #5a5a5a;
                               border-radius: 4px; padding: 6px 8px; width: 100%; box-sizing: border-box; }
.m-entry    { display: flex; align-items: center; gap: 8px; margin-bottom: 6px;
              background: #3a3a3a; padding: 7px 10px; border-radius: 4px; color: #ddd; }

/* ── Repeat panel ─────────────────────────── */
.rep-panel  { border: 1px solid #484848; border-radius: 6px; padding: 16px; margin-top: 24px; }
.rep-panel h4 { margin: 0 0 6px; color: #ddd; font-size: 1em; }
.rep-fields { display: flex; flex-wrap: wrap; gap: 12px; margin-bottom: 12px; }
.rep-fields label { font-size: .88em; color: #bbb; }
.rep-fields input { background: #3d3d3d; color: #eee; border: 1px solid #5a5a5a;
                    border-radius: 4px; padding: 5px 7px; display: block; margin-top: 3px; }
.rep-fields select { background: #3d3d3d; color: #eee; border: 1px solid #5a5a5a;
                     border-radius: 4px; padding: 5px 7px; display: block; margin-top: 3px; }
.dow-row    { display: flex; flex-wrap: wrap; gap: 6px; margin: 8px 0 14px; }
.dow-btn    { padding: 6px 11px; border: 1px solid #555; border-radius: 4px; cursor: pointer;
              background: #2d2d2d; color: #bbb; font-size: .82em; user-select: none; }
.dow-btn.on { background: #1a5fa8; border-color: #2a7fd8; color: #fff; }
</style>

<p style="font-size:.9em;color:#aaa;margin-bottom:14px;">Tap any day to add or edit shows. Red days are blackouts.</p>

<div style="display:flex;align-items:center;gap:10px;margin-bottom:10px;">
  <button class="btn btn-secondary btn-sm" onclick="prevMonth()">&#8249; Prev</button>
  <strong id="cal-title" style="flex:1;text-align:center;font-size:1.05em;"></strong>
  <button class="btn btn-secondary btn-sm" onclick="nextMonth()">Next &#8250;</button>
</div>

<div id="cal-wrap"><div id="calendar"></div></div>

<!-- Day modal -->
<div id="day-modal">
  <div class="m-box">
    <h3 id="modal-title"></h3>
    <div id="modal-entries"></div>
    <hr>
    <strong style="color:#ccc;font-size:.9em;">Add Show</strong>
    <div class="m-row" style="margin-top:10px;">
      <label>Time</label>
      <input type="time" id="new-time" value="19:00">
    </div>
    <div class="m-row">
      <label>Assignment</label>
      <select id="new-assign-type" onchange="toggleAssignType()">
        <option value="specific">Specific show</option>
        <option value="rotation">Rotation (alternating)</option>
      </select>
    </div>
    <div id="assign-specific" class="m-row">
      <label>Show</label>
      <select id="new-show-id">
        <?php foreach ($showDefs as $s): ?>
        <option value="<?= htmlspecialchars($s['id']) ?>"><?= htmlspecialchars($s['name']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div id="assign-rotation" style="display:none;" class="m-row">
      <p style="font-size:.82em;color:#999;margin:0 0 6px;">Select 2+ shows — they alternate each time this slot fires.</p>
      <?php foreach ($showDefs as $s): ?>
      <label style="display:flex;align-items:center;gap:6px;color:#ccc;font-size:.88em;margin-bottom:4px;">
        <input type="checkbox" class="rotation-cb" value="<?= htmlspecialchars($s['id']) ?>" style="width:auto;">
        <?= htmlspecialchars($s['name']) ?>
      </label>
      <?php endforeach; ?>
    </div>
    <button class="btn btn-success btn-sm" onclick="addShow()">Add to Day</button>
    <hr>
    <strong style="color:#ccc;font-size:.9em;">Blackout</strong>
    <p id="blackout-status" style="font-size:.88em;margin:8px 0 10px;color:#bbb;"></p>
    <button class="btn btn-warning btn-sm" id="blackout-btn" onclick="toggleBlackout()"></button>
    <hr>
    <button class="btn btn-secondary btn-sm" onclick="closeModal()">Close</button>
  </div>
</div>

<!-- Repeat scheduling -->
<div class="rep-panel">
  <h4>Schedule Repeating Show</h4>
  <p style="font-size:.85em;color:#999;margin:0 0 14px;">Add a show to every matching day in a date range at once.</p>
  <div class="rep-fields">
    <label>From<input type="date" id="rep-start"></label>
    <label>To<input type="date" id="rep-end"></label>
    <label>Time<input type="time" id="rep-time" value="19:00"></label>
    <label>Show
      <select id="rep-show">
        <?php foreach ($showDefs as $s): ?>
        <option value="<?= htmlspecialchars($s['id']) ?>"><?= htmlspecialchars($s['name']) ?></option>
        <?php endforeach; ?>
      </select>
    </label>
  </div>
  <div style="font-size:.85em;color:#bbb;margin-bottom:6px;">Days of week:</div>
  <div class="dow-row">
    <?php foreach (['Sun','Mon','Tue','Wed','Thu','Fri','Sat'] as $i => $d): ?>
    <span class="dow-btn" data-dow="<?= $i ?>" onclick="this.classList.toggle('on')"><?= $d ?></span>
    <?php endforeach; ?>
  </div>
  <button class="btn btn-primary btn-sm" onclick="scheduleRepeat()">Add to Calendar</button>
  <span id="rep-status" style="margin-left:10px;font-size:.85em;color:#aaa;"></span>
</div>

<script>
const SHOWS     = <?= json_encode(array_values($showDefs)) ?>;
const AJAX_BASE = '<?= "plugin.php?plugin=" . basename(__DIR__) . "&page=ajax.php&nopage=1" ?>';
let curYear   = <?= date('Y') ?>;
let curMonth  = <?= date('n') ?>;
let calData   = {};
let modalDate = null;

async function loadMonth(y, m) {
    curYear = y; curMonth = m;
    const res     = await fetch(`${AJAX_BASE}&action=get_month&year=${y}&month=${m}`);
    const entries = await res.json();
    calData = {};
    entries.forEach(e => { (calData[e.date] = calData[e.date] || []).push(e); });
    renderCalendar();
}

function renderCalendar() {
    document.getElementById('cal-title').textContent =
        new Date(curYear, curMonth - 1, 1).toLocaleString('default', {month:'long', year:'numeric'});

    const first  = new Date(curYear, curMonth - 1, 1).getDay();
    const days   = new Date(curYear, curMonth, 0).getDate();
    const today  = new Date().toISOString().slice(0, 10);

    let html = '<table id="cal-grid"><thead><tr>' +
        ['Su','Mo','Tu','We','Th','Fr','Sa'].map(d => `<th>${d}</th>`).join('') +
        '</tr></thead><tbody><tr>';

    for (let p = 0; p < first; p++) html += '<td class="is-empty"></td>';

    for (let d = 1; d <= days; d++) {
        const date    = `${curYear}-${String(curMonth).padStart(2,'0')}-${String(d).padStart(2,'0')}`;
        const entries = calData[date] || [];
        const blackout = entries.some(e => e.type === 'blackout');
        const shows    = entries.filter(e => e.type === 'show');
        const col      = (d + first - 1) % 7;

        if (col === 0 && d > 1) html += '</tr><tr>';

        let cls = blackout ? 'is-blackout' : '';
        if (date === today) cls += ' is-today';

        html += `<td class="${cls.trim()}" onclick="openDay('${date}')">` +
            `<div class="cal-num">${d}</div>` +
            shows.map(s => `<span class="cal-show">${s.time} ${label(s)}</span>`).join('') +
            (blackout ? '<div class="cal-blk">BLACKOUT</div>' : '') +
            '</td>';
    }

    document.getElementById('calendar').innerHTML = html + '</tr></tbody></table>';
}

function label(e) {
    if (e.show_id) { const s = SHOWS.find(x => x.id === e.show_id); return s ? s.name : e.show_id; }
    return '↻ ' + (e.rotation_ids || []).map(id => { const s = SHOWS.find(x => x.id === id); return s ? s.name : id; }).join('/');
}

function prevMonth() { curMonth--; if (curMonth < 1)  { curMonth = 12; curYear--; } loadMonth(curYear, curMonth); }
function nextMonth() { curMonth++; if (curMonth > 12) { curMonth = 1;  curYear++; } loadMonth(curYear, curMonth); }

function openDay(date) {
    modalDate = date;
    document.getElementById('modal-title').textContent =
        new Date(date + 'T12:00').toLocaleDateString('default', {weekday:'long', year:'numeric', month:'long', day:'numeric'});
    document.getElementById('day-modal').style.display = 'block';
    renderModalEntries();
    updateBlackoutBtn();
}
function closeModal() { document.getElementById('day-modal').style.display = 'none'; modalDate = null; }
document.getElementById('day-modal').addEventListener('click', e => { if (e.target === e.currentTarget) closeModal(); });

function renderModalEntries() {
    const shows = (calData[modalDate] || []).filter(e => e.type === 'show');
    document.getElementById('modal-entries').innerHTML = shows.length
        ? shows.map(e => `<div class="m-entry"><span>${e.time} — ${label(e)}</span>
            <button class="btn btn-danger btn-sm" style="margin-left:auto;padding:2px 7px;" onclick="deleteEntry('${e.id}')">✕</button></div>`).join('')
        : '<p style="color:#888;margin:0 0 4px;font-size:.88em;">No shows scheduled.</p>';
}

function updateBlackoutBtn() {
    const on  = (calData[modalDate] || []).some(e => e.type === 'blackout');
    document.getElementById('blackout-status').textContent = on ? '⛔ Blackout set — no shows will fire.' : 'No blackout set.';
    const btn = document.getElementById('blackout-btn');
    btn.textContent = on ? 'Remove Blackout' : 'Mark as Blackout';
    btn.className   = on ? 'btn btn-outline-warning btn-sm' : 'btn btn-warning btn-sm';
}

function toggleAssignType() {
    const t = document.getElementById('new-assign-type').value;
    document.getElementById('assign-specific').style.display = t === 'specific' ? '' : 'none';
    document.getElementById('assign-rotation').style.display = t === 'rotation'  ? '' : 'none';
}

async function addShow() {
    const time = document.getElementById('new-time').value || '19:00';
    const mode = document.getElementById('new-assign-type').value;
    const body = { date: modalDate, type: 'show', time };
    if (mode === 'specific') {
        body.show_id = document.getElementById('new-show-id').value;
        if (!body.show_id) { alert('Select a show.'); return; }
    } else {
        body.rotation_ids = [...document.querySelectorAll('.rotation-cb:checked')].map(c => c.value);
        if (body.rotation_ids.length < 2) { alert('Select at least 2 shows for rotation.'); return; }
    }
    const data = await postJSON('save_entry', body);
    (calData[modalDate] = calData[modalDate] || []).push(data.entry);
    renderModalEntries(); renderCalendar();
}

async function deleteEntry(id) {
    await fetch(`${AJAX_BASE}&action=delete_entry&id=${id}`);
    calData[modalDate] = (calData[modalDate] || []).filter(e => e.id !== id);
    renderModalEntries(); renderCalendar();
}

async function toggleBlackout() {
    const existing = (calData[modalDate] || []).find(e => e.type === 'blackout');
    if (existing) {
        await fetch(`${AJAX_BASE}&action=delete_entry&id=${existing.id}`);
        calData[modalDate] = (calData[modalDate] || []).filter(e => e.id !== existing.id);
    } else {
        const data = await postJSON('save_entry', { date: modalDate, type: 'blackout', reason: '' });
        (calData[modalDate] = calData[modalDate] || []).push(data.entry);
    }
    updateBlackoutBtn(); renderCalendar();
}

async function scheduleRepeat() {
    const start  = document.getElementById('rep-start').value;
    const end    = document.getElementById('rep-end').value;
    const time   = document.getElementById('rep-time').value || '19:00';
    const show   = document.getElementById('rep-show').value;
    const days   = [...document.querySelectorAll('.dow-btn.on')].map(el => +el.dataset.dow);
    const status = document.getElementById('rep-status');

    if (!start || !end)   { alert('Set a start and end date.'); return; }
    if (start > end)      { alert('Start must be before end.'); return; }
    if (!days.length)     { alert('Select at least one day of the week.'); return; }
    if (!show)            { alert('Select a show.'); return; }

    status.textContent = 'Adding…';
    const data = await postJSON('schedule_repeat', { start_date: start, end_date: end, time, days, show_id: show });
    status.textContent = `${data.count} show(s) added.`;
    loadMonth(curYear, curMonth);
}

async function postJSON(action, body) {
    const res = await fetch(`${AJAX_BASE}&action=${action}`, {
        method: 'POST', body: JSON.stringify(body), headers: {'Content-Type':'application/json'}
    });
    return res.json();
}

loadMonth(curYear, curMonth);
</script>

<?php
$playlists = [];
$playlistDir = "/home/fpp/media/playlists";
if (is_dir($playlistDir)) {
    foreach (glob("$playlistDir/*.json") as $f) $playlists[] = basename($f, '.json');
    sort($playlists);
}
$todayY = (int)date('Y');
$todayM = (int)date('n');
$AJAX_BASE = 'plugin.php?plugin=' . basename(__DIR__) . '&page=ajax.php&nopage=1';
?>
<style>
/* ── view toggle ──────────────────────────────────────── */
#view-btns .btn { min-width:60px; }
#view-btns .btn.active { background:#2a6496; color:#fff; border-color:#4a9fd4; }

/* ── shared calendar ──────────────────────────────────── */
#cal-wrap { overflow-x:auto; -webkit-overflow-scrolling:touch; }
.cal-num  { font-size:.82em; font-weight:700; margin-bottom:2px; }
.cal-show { display:block; font-size:.62em; background:#1d6b35; color:#cef5d8;
            border-radius:2px; padding:1px 3px; margin-top:2px;
            overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
.cal-show.rule { background:#1a4a7a; color:#b8d4f5; }
.cal-blk  { font-size:.62em; color:#f77; margin-top:2px; }

/* ── month grid ───────────────────────────────────────── */
#cal-month { table-layout:fixed; width:100%; min-width:280px;
             border-collapse:separate; border-spacing:2px; }
#cal-month th { padding:5px 2px; text-align:center; font-size:.72em;
                font-weight:600; color:#888; }
#cal-month td { border:1px solid #555; border-radius:3px; padding:5px 4px;
                vertical-align:top; cursor:pointer; min-height:62px;
                background:#2d2d2d; color:#ddd; }
#cal-month td:hover    { background:#383838; }
#cal-month td.today    { border:2px solid #4a9fd4; }
#cal-month td.blackout { background:#4a1515; }
#cal-month td.empty    { background:transparent; border:none; cursor:default; }

/* ── week grid ────────────────────────────────────────── */
#cal-week { table-layout:fixed; width:100%; min-width:420px;
            border-collapse:separate; border-spacing:2px; }
#cal-week th { padding:5px 2px; text-align:center; font-size:.72em;
               font-weight:600; color:#888; }
#cal-week td { border:1px solid #555; border-radius:3px; padding:6px 4px;
               vertical-align:top; cursor:pointer; min-height:80px;
               background:#2d2d2d; color:#ddd; }
#cal-week td:hover    { background:#383838; }
#cal-week td.today    { border:2px solid #4a9fd4; }
#cal-week td.blackout { background:#4a1515; }

/* ── day view ─────────────────────────────────────────── */
#day-view { background:#2d2d2d; border-radius:6px; padding:14px; }
.dv-entry { display:flex; align-items:center; padding:6px 8px;
            border-radius:4px; background:#1d3d1d; margin-bottom:6px; }
.dv-entry.rule    { background:#1a3050; }
.dv-entry.blackout { background:#4a1515; }
.dv-time  { font-size:.82em; color:#aaa; min-width:48px; }
.dv-label { flex:1; font-size:.9em; margin:0 8px; }

/* ── day modal ────────────────────────────────────────── */
#day-modal { display:none; position:fixed; inset:0; background:rgba(0,0,0,.72);
             z-index:9999; overflow-y:auto; padding:16px 10px; }
.m-box     { background:#2d2d2d; color:#e8e8e8; border-radius:8px;
             padding:20px 16px; max-width:460px; margin:0 auto; }
.m-entry   { display:flex; align-items:center; padding:6px 8px;
             background:#1d3d1d; border-radius:4px; margin-bottom:6px;
             border-left:3px solid #1d6b35; }
.m-entry.rule     { background:#1a3050; border-left-color:#4a9fd4; }
.m-entry.blackout { background:#4a1515; border-left-color:#f55; }
.m-time { font-size:.78em; color:#aaa; min-width:38px; }
.m-lbl  { flex:1; font-size:.88em; margin:0 8px; }

/* ── rules list ───────────────────────────────────────── */
.rule-card       { background:#2a2a3a; border:1px solid #4a4a6a;
                   border-radius:6px; padding:10px 12px; margin-bottom:8px; }
.rule-card .rc-title { font-weight:600; margin-bottom:2px; }
.rule-card .rc-meta  { font-size:.78em; color:#aaa; }

/* ── day-of-week toggle buttons ───────────────────────── */
.dow-wrap { display:flex; flex-wrap:wrap; }
.dow-btn  { min-width:38px; padding:3px 2px; font-size:.78em; text-align:center;
            background:#444; color:#ccc; border:1px solid #666; border-radius:3px;
            cursor:pointer; margin:0 3px 3px 0; }
.dow-btn.on { background:#2a6496; color:#fff; border-color:#4a9fd4; }

/* ── edit-rule banner ─────────────────────────────────── */
#edit-banner { display:none; background:#1a4a1a; border:1px solid #2d8a2d;
               border-radius:4px; padding:6px 12px; margin-bottom:10px;
               font-size:.88em; }

/* ── misc ─────────────────────────────────────────────── */
.btn-xs { padding:1px 6px; font-size:.72em; line-height:1.5; border-radius:2px; }
.form-control, select.form-control { background:#1e1e1e; color:#e0e0e0; border-color:#555; }
.form-control:focus { border-color:#4a9fd4; box-shadow:none; }
</style>

<!-- ── View toggle + nav ─────────────────────────────────── -->
<div class="d-flex align-items-center flex-wrap mb-3">
  <div id="view-btns" class="btn-group mr-3">
    <button class="btn btn-sm btn-outline-secondary" id="btn-month" onclick="setView('month')">Month</button>
    <button class="btn btn-sm btn-outline-secondary" id="btn-week"  onclick="setView('week')">Week</button>
    <button class="btn btn-sm btn-outline-secondary" id="btn-day"   onclick="setView('day')">Day</button>
  </div>
  <button class="btn btn-sm btn-secondary mr-1" onclick="prevPeriod()">&#8249;</button>
  <span id="cal-title" class="mx-2 font-weight-bold" style="min-width:190px;text-align:center"></span>
  <button class="btn btn-sm btn-secondary mr-3" onclick="nextPeriod()">&#8250;</button>
  <button class="btn btn-sm btn-outline-info ml-auto" onclick="goToday()">Today</button>
</div>

<!-- ── Calendar area ──────────────────────────────────────── -->
<div id="cal-wrap">
  <table id="cal-month" style="display:none">
    <thead><tr id="month-head"></tr></thead>
    <tbody id="month-body"></tbody>
  </table>
  <table id="cal-week" style="display:none">
    <thead><tr id="week-head"></tr></thead>
    <tbody><tr id="week-body"></tr></tbody>
  </table>
  <div id="day-view" style="display:none">
    <div id="day-entries"></div>
    <button class="btn btn-sm btn-outline-success mt-2"
            onclick="openDay(fmtDate(curDayDate))">+ Add Show / Blackout</button>
  </div>
</div>

<!-- ── Day modal ─────────────────────────────────────────── -->
<div id="day-modal">
  <div class="m-box">
    <div class="d-flex justify-content-between align-items-center mb-3">
      <strong id="modal-title"></strong>
      <button class="btn btn-sm btn-secondary" onclick="closeModal()">&#10005;</button>
    </div>
    <div id="modal-entries"></div>
    <hr style="border-color:#555">
    <div class="mb-2"><strong style="font-size:.9em">Add to this day</strong></div>
    <div class="form-row mb-2">
      <div class="col-4">
        <input type="time" id="m-time" class="form-control form-control-sm" value="19:00">
      </div>
      <div class="col-8">
        <select id="m-playlist" class="form-control form-control-sm">
          <?php foreach ($playlists as $p): ?>
          <option value="<?= htmlspecialchars($p) ?>"><?= htmlspecialchars($p) ?></option>
          <?php endforeach; ?>
          <?php if (!$playlists): ?>
          <option value="">(no playlists found)</option>
          <?php endif; ?>
        </select>
      </div>
    </div>
    <button class="btn btn-sm btn-success mr-1" onclick="addShow()">Add Show</button>
    <button class="btn btn-sm btn-outline-danger" onclick="addBlackout()">Blackout Day</button>
  </div>
</div>

<hr style="border-color:#444;margin:28px 0 18px">

<!-- ── Repeating rule form ────────────────────────────────── -->
<div class="mb-4">
  <h5 class="mb-3">Repeating Schedule Rule</h5>
  <div id="edit-banner">
    Editing: <strong id="edit-rule-label"></strong>
    <button class="btn btn-xs btn-outline-secondary ml-2" onclick="cancelRuleEdit()">Cancel</button>
  </div>
  <input type="hidden" id="rule-id">

  <div class="form-row mb-2">
    <div class="col-sm-6 mb-2">
      <label class="small mb-1">Start Date</label>
      <input type="date" id="rule-start" class="form-control form-control-sm">
    </div>
    <div class="col-sm-6 mb-2">
      <label class="small mb-1">End Date</label>
      <input type="date" id="rule-end" class="form-control form-control-sm">
    </div>
  </div>

  <div class="mb-2">
    <label class="small mb-1">Days of Week</label>
    <div class="dow-wrap" id="dow-btns">
      <span class="dow-btn on" data-d="0">Sun</span>
      <span class="dow-btn on" data-d="1">Mon</span>
      <span class="dow-btn on" data-d="2">Tue</span>
      <span class="dow-btn on" data-d="3">Wed</span>
      <span class="dow-btn on" data-d="4">Thu</span>
      <span class="dow-btn on" data-d="5">Fri</span>
      <span class="dow-btn on" data-d="6">Sat</span>
    </div>
  </div>

  <div class="form-row mb-2">
    <div class="col-sm-4 mb-2">
      <label class="small mb-1">Window Start</label>
      <input type="time" id="rule-wstart" class="form-control form-control-sm" value="19:00">
    </div>
    <div class="col-sm-4 mb-2">
      <label class="small mb-1">Window End <span class="text-muted">(optional)</span></label>
      <input type="time" id="rule-wend" class="form-control form-control-sm">
    </div>
    <div class="col-sm-4 mb-2">
      <label class="small mb-1">Interval <span class="text-muted">(mins, optional)</span></label>
      <input type="number" id="rule-interval" class="form-control form-control-sm"
             min="1" max="1440" placeholder="e.g. 30">
    </div>
  </div>
  <small class="text-muted d-block mb-2">
    If Window End + Interval are set, shows repeat every N minutes from start to end.
    The last show fires at or before the window end time.
  </small>

  <div class="mb-3">
    <label class="small mb-1">Playlist</label>
    <select id="rule-playlist" class="form-control form-control-sm">
      <?php foreach ($playlists as $p): ?>
      <option value="<?= htmlspecialchars($p) ?>"><?= htmlspecialchars($p) ?></option>
      <?php endforeach; ?>
      <?php if (!$playlists): ?>
      <option value="">(no playlists found)</option>
      <?php endif; ?>
    </select>
  </div>

  <button class="btn btn-sm btn-primary" onclick="saveRule()">Save Rule</button>
</div>

<!-- ── Active rules list ──────────────────────────────────── -->
<div class="mb-4">
  <h5 class="mb-3">Active Rules</h5>
  <div id="rules-list"><em class="text-muted small">No rules defined.</em></div>
</div>

<!-- ── One-off entry form ─────────────────────────────────── -->
<div class="mb-4">
  <h5 class="mb-3">One-off Entry</h5>
  <div class="form-row mb-2">
    <div class="col-sm-3 mb-2">
      <label class="small mb-1">Date</label>
      <input type="date" id="oo-date" class="form-control form-control-sm">
    </div>
    <div class="col-sm-2 mb-2">
      <label class="small mb-1">Time</label>
      <input type="time" id="oo-time" class="form-control form-control-sm" value="19:00">
    </div>
    <div class="col-sm-4 mb-2">
      <label class="small mb-1">Playlist</label>
      <select id="oo-playlist" class="form-control form-control-sm">
        <?php foreach ($playlists as $p): ?>
        <option value="<?= htmlspecialchars($p) ?>"><?= htmlspecialchars($p) ?></option>
        <?php endforeach; ?>
        <?php if (!$playlists): ?>
        <option value="">(no playlists found)</option>
        <?php endif; ?>
      </select>
    </div>
    <div class="col-sm-3 mb-2 d-flex align-items-end">
      <button class="btn btn-sm btn-success mr-1" onclick="addOneOff()">Add Show</button>
      <button class="btn btn-sm btn-outline-danger" onclick="addOneOff('blackout')">Blackout</button>
    </div>
  </div>
</div>
<script>
const AJAX     = '<?= $AJAX_BASE ?>';
const DOW_LBL  = ['Sun','Mon','Tue','Wed','Thu','Fri','Sat'];
const MON_LBL  = ['January','February','March','April','May','June',
                  'July','August','September','October','November','December'];

let curView      = 'month';
let curYear      = <?= $todayY ?>;
let curMonth     = <?= $todayM ?>;   // 1-based
let curWeekStart = getSundayOf(new Date());
let curDayDate   = new Date();
let dataCache    = {};               // 'YYYY-MM' -> {entries, rules}
let calData      = {byDate:{}, rules:[]};
let modalDate    = null;

// ── date utilities ───────────────────────────────────────
function fmtDate(d) {
    return d.getFullYear() + '-' +
           String(d.getMonth()+1).padStart(2,'0') + '-' +
           String(d.getDate()).padStart(2,'0');
}
function getSundayOf(d) {
    const s = new Date(d);
    s.setDate(s.getDate() - s.getDay());
    s.setHours(0,0,0,0);
    return s;
}
function addDays(d, n) { const r = new Date(d); r.setDate(r.getDate()+n); return r; }
function isToday(ds)   { return ds === fmtDate(new Date()); }
function monthKey(y,m) { return y + '-' + String(m).padStart(2,'0'); }
function ym(d)         { return [d.getFullYear(), d.getMonth()+1]; }

// ── data layer ───────────────────────────────────────────
async function fetchMonth(y, m) {
    const key = monthKey(y, m);
    if (dataCache[key]) return dataCache[key];
    const r = await fetch(AJAX + '&action=get_month&year=' + y + '&month=' + m);
    const d = await r.json();
    dataCache[key] = d;
    return d;
}
function invalidateCache() { dataCache = {}; }

function buildCalData(monthList) {
    calData = {byDate:{}, rules:[]};
    for (const md of monthList) {
        for (const e of (md.entries || [])) {
            if (!calData.byDate[e.date]) calData.byDate[e.date] = [];
            calData.byDate[e.date].push(e);
        }
        for (const r of (md.rules || [])) {
            if (!calData.rules.find(x => x.id === r.id)) calData.rules.push(r);
        }
    }
    for (const dt of Object.keys(calData.byDate)) {
        calData.byDate[dt].sort((a,b) => (a.time||'').localeCompare(b.time||''));
    }
}

// ── loaders ──────────────────────────────────────────────
async function loadMonth() {
    buildCalData([await fetchMonth(curYear, curMonth)]);
    renderMonth(); renderRules();
}
async function loadWeek() {
    const we = addDays(curWeekStart, 6);
    const [sy,sm] = ym(curWeekStart), [ey,em] = ym(we);
    const pairs = [[sy,sm]];
    if (sy !== ey || sm !== em) pairs.push([ey,em]);
    buildCalData(await Promise.all(pairs.map(([y,m]) => fetchMonth(y,m))));
    renderWeek(); renderRules();
}
async function loadDay() {
    const [y,m] = ym(curDayDate);
    buildCalData([await fetchMonth(y,m)]);
    renderDay(); renderRules();
}

// ── view control ─────────────────────────────────────────
function setView(v) {
    curView = v;
    ['month','week','day'].forEach(n => {
        document.getElementById('btn-'+n).classList.toggle('active', n===v);
    });
    document.getElementById('cal-month').style.display = v==='month' ? '' : 'none';
    document.getElementById('cal-week').style.display  = v==='week'  ? '' : 'none';
    document.getElementById('day-view').style.display  = v==='day'   ? '' : 'none';
    refreshView();
}
function refreshView() {
    if (curView==='month') loadMonth();
    else if (curView==='week') loadWeek();
    else loadDay();
}
function prevPeriod() {
    if (curView==='month') { curMonth--; if (curMonth<1){curMonth=12;curYear--;} }
    else if (curView==='week') { curWeekStart = addDays(curWeekStart,-7); }
    else { curDayDate = addDays(curDayDate,-1); }
    refreshView();
}
function nextPeriod() {
    if (curView==='month') { curMonth++; if (curMonth>12){curMonth=1;curYear++;} }
    else if (curView==='week') { curWeekStart = addDays(curWeekStart,7); }
    else { curDayDate = addDays(curDayDate,1); }
    refreshView();
}
function goToday() {
    const t = new Date();
    curYear=t.getFullYear(); curMonth=t.getMonth()+1;
    curWeekStart=getSundayOf(t); curDayDate=new Date(t);
    refreshView();
}
function updateTitle() {
    const el = document.getElementById('cal-title');
    if (curView==='month') {
        el.textContent = MON_LBL[curMonth-1] + ' ' + curYear;
    } else if (curView==='week') {
        el.textContent = fmtDate(curWeekStart) + ' – ' + fmtDate(addDays(curWeekStart,6));
    } else {
        el.textContent = fmtDate(curDayDate);
    }
}

// ── renderers ─────────────────────────────────────────────
function entryLabel(e) {
    if (e.type==='blackout') return '✖ Blackout';
    return e.playlist || (e.playlists||[]).join(' / ') || '(show)';
}
function escH(s) {
    return String(s||'')
        .replace(/&/g,'&amp;').replace(/</g,'&lt;')
        .replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

function renderMonth() {
    updateTitle();
    document.getElementById('month-head').innerHTML =
        DOW_LBL.map(d => '<th>'+d+'</th>').join('');
    const first = new Date(curYear, curMonth-1, 1).getDay();
    const days  = new Date(curYear, curMonth, 0).getDate();
    const prefix = monthKey(curYear, curMonth);
    let html='', col=0, row='<tr>';
    for (let i=0; i<first; i++) { row += '<td class="empty"></td>'; col++; }
    for (let d=1; d<=days; d++) {
        const ds   = prefix + '-' + String(d).padStart(2,'0');
        const ents = calData.byDate[ds] || [];
        const blk  = ents.some(e=>e.type==='blackout');
        let cls = blk?'blackout':''; if (isToday(ds)) cls += (cls?' ':'')+'today';
        row += `<td class="${cls}" onclick="openDay('${ds}')">`;
        row += `<div class="cal-num">${d}</div>`;
        for (const e of ents) {
            if (e.type==='blackout') { row+=`<span class="cal-blk">✖ Blackout</span>`; continue; }
            row += `<span class="cal-show${e.rule_id?' rule':''}">${escH(entryLabel(e))}</span>`;
        }
        row += '</td>';
        if (++col===7) { html+=row+'</tr>'; row='<tr>'; col=0; }
    }
    if (col>0) { while(col<7){row+='<td class="empty"></td>';col++;} html+=row+'</tr>'; }
    document.getElementById('month-body').innerHTML = html;
}

function renderWeek() {
    updateTitle();
    const today = fmtDate(new Date());
    let hd='', bd='';
    for (let i=0; i<7; i++) {
        const d  = addDays(curWeekStart, i);
        const ds = fmtDate(d);
        const ents = calData.byDate[ds] || [];
        const blk  = ents.some(e=>e.type==='blackout');
        let cls = blk?'blackout':''; if (ds===today) cls+=(cls?' ':'')+'today';
        hd += `<th>${DOW_LBL[d.getDay()]}<br><small>${ds.slice(5)}</small></th>`;
        bd += `<td class="${cls}" onclick="openDay('${ds}')">`;
        for (const e of ents) {
            if (e.type==='blackout') { bd+=`<span class="cal-blk">✖</span>`; continue; }
            bd += `<span class="cal-show${e.rule_id?' rule':''}">${escH(e.time||'')} ${escH(entryLabel(e))}</span>`;
        }
        bd += '</td>';
    }
    document.getElementById('week-head').innerHTML = hd;
    document.getElementById('week-body').innerHTML = bd;
}

function renderDay() {
    updateTitle();
    const ds   = fmtDate(curDayDate);
    const ents = calData.byDate[ds] || [];
    const el   = document.getElementById('day-entries');
    if (!ents.length) { el.innerHTML='<p class="text-muted small">No entries for this day.</p>'; return; }
    let html='';
    for (const e of ents) {
        const isRule = !!e.rule_id, isBlk = e.type==='blackout';
        html += `<div class="dv-entry ${isBlk?'blackout':(isRule?'rule':'')}">`;
        html += `<span class="dv-time">${escH(e.time||'')}</span>`;
        html += `<span class="dv-label">${escH(entryLabel(e))}</span>`;
        if (isRule) {
            html += `<button class="btn btn-xs btn-outline-info mr-1" onclick="editRule('${escH(e.rule_id)}')">Edit Rule</button>`;
            html += `<button class="btn btn-xs btn-outline-danger" onclick="deleteRuleConfirm('${escH(e.rule_id)}')">Del Rule</button>`;
        } else {
            html += `<button class="btn btn-xs btn-outline-danger" onclick="deleteEntry('${escH(e.id)}')">&#10005;</button>`;
        }
        html += '</div>';
    }
    el.innerHTML = html;
}

function renderRules() {
    const el = document.getElementById('rules-list');
    if (!calData.rules.length) {
        el.innerHTML='<em class="text-muted small">No rules defined.</em>'; return;
    }
    let html='';
    for (const r of calData.rules) {
        const days = (r.days||[0,1,2,3,4,5,6]).map(d=>DOW_LBL[d]).join(', ');
        let timing = r.window_start || '?';
        if (r.window_end)    timing += ' – ' + r.window_end;
        if (r.interval_mins) timing += ' every ' + r.interval_mins + ' min';
        const pl = r.playlist || (r.playlists||[]).join(' / ') || '–';
        html += `<div class="rule-card">
          <div class="rc-title">${escH(pl)}</div>
          <div class="rc-meta">${escH(r.start_date)} → ${escH(r.end_date)}</div>
          <div class="rc-meta">${escH(days)} &nbsp;|&nbsp; ${escH(timing)}</div>
          <div class="mt-2">
            <button class="btn btn-xs btn-outline-info mr-1" onclick="editRule('${r.id}')">Edit</button>
            <button class="btn btn-xs btn-outline-danger" onclick="deleteRuleConfirm('${r.id}')">Delete</button>
          </div>
        </div>`;
    }
    el.innerHTML = html;
}

// ── day modal ─────────────────────────────────────────────
function openDay(ds) {
    modalDate = ds;
    document.getElementById('modal-title').textContent = ds;
    renderModalEntries(ds);
    document.getElementById('day-modal').style.display = 'block';
}
function closeModal() {
    document.getElementById('day-modal').style.display = 'none';
    modalDate = null;
}
document.getElementById('day-modal').addEventListener('click', function(ev) {
    if (ev.target === this) closeModal();
});

function renderModalEntries(ds) {
    const ents = calData.byDate[ds] || [];
    const el   = document.getElementById('modal-entries');
    if (!ents.length) { el.innerHTML='<p class="text-muted small mb-2">No entries yet.</p>'; return; }
    let html='';
    for (const e of ents) {
        const isRule = !!e.rule_id, isBlk = e.type==='blackout';
        html += `<div class="m-entry ${isBlk?'blackout':(isRule?'rule':'')}">`;
        html += `<span class="m-time">${escH(e.time||'')}</span>`;
        html += `<span class="m-lbl">${escH(entryLabel(e))}</span>`;
        if (isRule) {
            html += `<button class="btn btn-xs btn-outline-info mr-1" onclick="editRule('${escH(e.rule_id)}');closeModal()">Edit Rule</button>`;
            html += `<button class="btn btn-xs btn-outline-danger" onclick="deleteRuleModal('${escH(e.rule_id)}')">Del Rule</button>`;
        } else {
            html += `<button class="btn btn-xs btn-outline-danger" onclick="deleteEntry('${escH(e.id)}')">&#10005;</button>`;
        }
        html += '</div>';
    }
    el.innerHTML = html;
}

// ── modal CRUD ────────────────────────────────────────────
async function addShow() {
    if (!modalDate) return;
    const body = {
        date:     modalDate,
        type:     'show',
        time:     document.getElementById('m-time').value,
        playlist: document.getElementById('m-playlist').value,
    };
    await fetch(AJAX+'&action=save_entry', {method:'POST', body:JSON.stringify(body)});
    invalidateCache();
    await reloadAndRefreshModal(modalDate);
}
async function addBlackout() {
    if (!modalDate) return;
    if (!confirm('Mark ' + modalDate + ' as a blackout day?')) return;
    await fetch(AJAX+'&action=save_entry', {method:'POST', body:JSON.stringify({date:modalDate, type:'blackout'})});
    invalidateCache();
    await reloadAndRefreshModal(modalDate);
}
async function deleteEntry(id) {
    if (!confirm('Delete this entry?')) return;
    await fetch(AJAX+'&action=delete_entry&id='+encodeURIComponent(id));
    invalidateCache();
    const ds = modalDate;
    await refreshView_async();
    if (ds) renderModalEntries(ds);
}
async function reloadAndRefreshModal(ds) {
    await refreshView_async();
    renderModalEntries(ds);
}

// ── one-off form ──────────────────────────────────────────
async function addOneOff(type) {
    const date = document.getElementById('oo-date').value;
    if (!date) { alert('Pick a date first.'); return; }
    let body;
    if (type === 'blackout') {
        if (!confirm('Mark ' + date + ' as a blackout?')) return;
        body = {date, type:'blackout'};
    } else {
        body = {
            date, type:'show',
            time:     document.getElementById('oo-time').value,
            playlist: document.getElementById('oo-playlist').value,
        };
    }
    await fetch(AJAX+'&action=save_entry', {method:'POST', body:JSON.stringify(body)});
    invalidateCache();
    refreshView();
}

// ── rule form ─────────────────────────────────────────────
document.querySelectorAll('.dow-btn').forEach(b =>
    b.addEventListener('click', () => b.classList.toggle('on'))
);
function getSelectedDays() {
    return [...document.querySelectorAll('.dow-btn.on')].map(b => parseInt(b.dataset.d));
}
function setSelectedDays(days) {
    document.querySelectorAll('.dow-btn').forEach(b =>
        b.classList.toggle('on', days.includes(parseInt(b.dataset.d)))
    );
}

async function saveRule() {
    const start = document.getElementById('rule-start').value;
    const end   = document.getElementById('rule-end').value;
    const wst   = document.getElementById('rule-wstart').value;
    const wend  = document.getElementById('rule-wend').value;
    const iv    = document.getElementById('rule-interval').value;
    const pl    = document.getElementById('rule-playlist').value;
    const days  = getSelectedDays();
    if (!start || !end || !wst) { alert('Start date, end date and window start are required.'); return; }
    if (!days.length)           { alert('Select at least one day of the week.'); return; }
    const body = {start_date:start, end_date:end, days, window_start:wst, playlist:pl};
    const rid  = document.getElementById('rule-id').value;
    if (rid)  body.id           = rid;
    if (wend) body.window_end   = wend;
    if (iv)   body.interval_mins = parseInt(iv);
    const resp = await fetch(AJAX+'&action=save_rule', {method:'POST', body:JSON.stringify(body)});
    const json = await resp.json();
    if (!json.ok) { alert('Save failed: ' + (json.error||'unknown')); return; }
    cancelRuleEdit();
    invalidateCache();
    refreshView();
}

function editRule(ruleId) {
    const r = calData.rules.find(x => x.id === ruleId);
    if (!r) { alert('Rule not found — try refreshing the page.'); return; }
    document.getElementById('rule-id').value      = r.id;
    document.getElementById('rule-start').value   = r.start_date  || '';
    document.getElementById('rule-end').value     = r.end_date    || '';
    document.getElementById('rule-wstart').value  = r.window_start|| '';
    document.getElementById('rule-wend').value    = r.window_end  || '';
    document.getElementById('rule-interval').value = r.interval_mins || '';
    const pl = r.playlist || (r.playlists||[])[0] || '';
    document.getElementById('rule-playlist').value = pl;
    setSelectedDays(r.days || [0,1,2,3,4,5,6]);
    document.getElementById('edit-rule-label').textContent = pl || r.id;
    document.getElementById('edit-banner').style.display = 'block';
    document.getElementById('rule-start').scrollIntoView({behavior:'smooth', block:'center'});
}

function cancelRuleEdit() {
    document.getElementById('rule-id').value       = '';
    document.getElementById('rule-start').value    = '';
    document.getElementById('rule-end').value      = '';
    document.getElementById('rule-wstart').value   = '19:00';
    document.getElementById('rule-wend').value     = '';
    document.getElementById('rule-interval').value = '';
    setSelectedDays([0,1,2,3,4,5,6]);
    document.getElementById('edit-banner').style.display = 'none';
}

async function deleteRuleConfirm(ruleId) {
    if (!confirm('Delete this repeating rule and all its generated shows?')) return;
    await fetch(AJAX+'&action=delete_rule&id='+encodeURIComponent(ruleId));
    invalidateCache();
    refreshView();
}
async function deleteRuleModal(ruleId) {
    closeModal();
    await deleteRuleConfirm(ruleId);
}

// ── async refresh helper ──────────────────────────────────
function refreshView_async() {
    if (curView==='month') return loadMonth();
    if (curView==='week')  return loadWeek();
    return loadDay();
}

// ── init ──────────────────────────────────────────────────
setView('month');
</script>

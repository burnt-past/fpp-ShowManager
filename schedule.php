<?php
$scheduleFile = $settings['configDirectory'] . "/ShowManagerSchedule.config";
$showsFile    = $settings['configDirectory'] . "/ShowManagerShows.config";

// ---- Load shows for dropdowns ----
$showsData = file_exists($showsFile) ? json_decode(file_get_contents($showsFile), true) : [];
$showDefs  = $showsData['shows'] ?? [];

// ---- AJAX handlers ----
if (isset($_GET['action'])) {
    header('Content-Type: application/json');

    $schedule = file_exists($scheduleFile)
        ? (json_decode(file_get_contents($scheduleFile), true) ?? ['entries' => []])
        : ['entries' => []];

    switch ($_GET['action']) {

        case 'get_month': {
            $year  = (int)($_GET['year']  ?? date('Y'));
            $month = (int)($_GET['month'] ?? date('n'));
            $prefix = sprintf('%04d-%02d', $year, $month);
            $entries = array_values(array_filter(
                $schedule['entries'] ?? [],
                fn($e) => str_starts_with($e['date'], $prefix)
            ));
            echo json_encode($entries);
            exit;
        }

        case 'save_entry': {
            $body = json_decode(file_get_contents('php://input'), true);
            if (!$body || empty($body['date']) || empty($body['type'])) {
                http_response_code(400); echo json_encode(['error' => 'invalid']); exit;
            }
            $entry = [
                'id'   => $body['id'] ?? uniqid('e_'),
                'date' => $body['date'],
                'type' => $body['type'],
            ];
            if ($body['type'] === 'show') {
                $entry['time']         = $body['time'] ?? '19:00';
                if (!empty($body['show_id'])) {
                    $entry['show_id']  = $body['show_id'];
                } else {
                    $entry['rotation_ids'] = $body['rotation_ids'] ?? [];
                }
            } elseif ($body['type'] === 'blackout') {
                $entry['reason'] = $body['reason'] ?? '';
            }

            // Replace if same id, otherwise append
            $entries = $schedule['entries'] ?? [];
            $replaced = false;
            foreach ($entries as &$e) {
                if ($e['id'] === $entry['id']) { $e = $entry; $replaced = true; break; }
            }
            if (!$replaced) $entries[] = $entry;
            $schedule['entries'] = $entries;
            file_put_contents($scheduleFile, json_encode($schedule, JSON_PRETTY_PRINT));
            echo json_encode(['ok' => true, 'entry' => $entry]);
            exit;
        }

        case 'delete_entry': {
            $id = $_GET['id'] ?? '';
            $schedule['entries'] = array_values(array_filter(
                $schedule['entries'] ?? [],
                fn($e) => $e['id'] !== $id
            ));
            file_put_contents($scheduleFile, json_encode($schedule, JSON_PRETTY_PRINT));
            echo json_encode(['ok' => true]);
            exit;
        }
    }
    http_response_code(400); echo json_encode(['error' => 'unknown action']); exit;
}
?>

<p>Click any day to add or edit shows. Days highlighted in red are blackout days (no shows).</p>

<div style="display:flex;align-items:center;gap:12px;margin-bottom:12px;">
  <button class="buttons" onclick="prevMonth()">&#8249; Prev</button>
  <strong id="cal-title" style="font-size:1.2em;min-width:160px;text-align:center;"></strong>
  <button class="buttons" onclick="nextMonth()">Next &#8250;</button>
</div>

<div id="calendar" style="width:100%;max-width:820px;"></div>

<!-- Day detail modal -->
<div id="day-modal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.6);z-index:9999;align-items:center;justify-content:center;">
  <div style="background:#222;color:#eee;border-radius:8px;padding:24px;min-width:380px;max-width:520px;width:90%;">
    <h3 id="modal-title" style="margin:0 0 16px"></h3>

    <div id="modal-entries"></div>

    <hr style="border-color:#444;margin:16px 0;">

    <div id="add-show-form">
      <strong>Add Show</strong><br><br>
      <label>Time: <input type="time" id="new-time" value="19:00"></label><br><br>
      <label>Playlist assignment:<br>
        <select id="new-assign-type" onchange="toggleAssignType()" style="margin-top:4px;">
          <option value="specific">Specific show</option>
          <option value="rotation">Rotation (alternating)</option>
        </select>
      </label><br><br>

      <div id="assign-specific">
        <label>Show:
          <select id="new-show-id">
            <?php foreach ($showDefs as $s): ?>
            <option value="<?= htmlspecialchars($s['id']) ?>"><?= htmlspecialchars($s['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </label>
      </div>

      <div id="assign-rotation" style="display:none;">
        <p style="margin:0 0 6px;font-size:.85em;color:#aaa;">
          Select two or more shows — they'll alternate in order each time this slot fires.
        </p>
        <?php foreach ($showDefs as $s): ?>
        <label style="display:block;">
          <input type="checkbox" class="rotation-cb" value="<?= htmlspecialchars($s['id']) ?>">
          <?= htmlspecialchars($s['name']) ?>
        </label>
        <?php endforeach; ?>
      </div>

      <br>
      <button class="buttons" onclick="addShow()">Add Show to Day</button>
    </div>

    <br>
    <div id="blackout-section">
      <strong>Blackout</strong><br><br>
      <div id="blackout-status"></div>
      <button class="buttons" id="blackout-btn" onclick="toggleBlackout()"></button>
    </div>

    <br>
    <button class="buttons" onclick="closeModal()">Close</button>
  </div>
</div>

<script>
const SHOWS     = <?= json_encode(array_values($showDefs)) ?>;
const AJAX_BASE = '<?= "plugin.php?plugin=" . basename(__DIR__) . "&page=index.php&tab=schedule" ?>';
let curYear  = <?= date('Y') ?>;
let curMonth = <?= date('n') ?>;
let calData  = {};   // date -> [entry, ...]
let modalDate = null;

// ---------------------------------------------------------------------------
// Calendar render
// ---------------------------------------------------------------------------

async function loadMonth(year, month) {
    curYear = year; curMonth = month;
    const res  = await fetch(`${AJAX_BASE}&action=get_month&year=${year}&month=${month}`);
    const entries = await res.json();
    calData = {};
    entries.forEach(e => {
        if (!calData[e.date]) calData[e.date] = [];
        calData[e.date].push(e);
    });
    renderCalendar();
}

function renderCalendar() {
    const title = document.getElementById('cal-title');
    title.textContent = new Date(curYear, curMonth - 1, 1)
        .toLocaleString('default', {month: 'long', year: 'numeric'});

    const firstDay = new Date(curYear, curMonth - 1, 1).getDay();
    const daysInMonth = new Date(curYear, curMonth, 0).getDate();
    const today = new Date().toISOString().slice(0, 10);

    let html = `<table style="width:100%;border-collapse:collapse;">
      <thead><tr>${['Sun','Mon','Tue','Wed','Thu','Fri','Sat']
        .map(d => `<th style="padding:6px;text-align:center;color:#aaa;">${d}</th>`).join('')}</tr></thead>
      <tbody><tr>`;

    for (let pad = 0; pad < firstDay; pad++) html += '<td></td>';

    for (let d = 1; d <= daysInMonth; d++) {
        const dateStr = `${curYear}-${String(curMonth).padStart(2,'0')}-${String(d).padStart(2,'0')}`;
        const entries = calData[dateStr] || [];
        const isToday = dateStr === today;
        const isBlackout = entries.some(e => e.type === 'blackout');
        const shows = entries.filter(e => e.type === 'show');

        let bg = isBlackout ? '#5a1a1a' : (isToday ? '#1a3a5a' : '#2a2a2a');
        let html2 = shows.map(s => {
            const label = resolveShowLabel(s);
            return `<div style="font-size:.7em;background:#1a5a2a;border-radius:3px;padding:1px 4px;margin-top:2px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">${s.time} ${label}</div>`;
        }).join('');
        if (isBlackout) html2 += `<div style="font-size:.7em;color:#ff8888;">BLACKOUT</div>`;

        const col = (d + firstDay - 1) % 7;
        if (col === 0 && d > 1) html += '</tr><tr>';

        html += `<td onclick="openDay('${dateStr}')" style="cursor:pointer;border:1px solid #444;padding:6px;vertical-align:top;min-height:70px;background:${bg};border-radius:4px;">
          <div style="font-weight:bold;margin-bottom:2px;">${d}</div>${html2}</td>`;
    }

    html += '</tr></tbody></table>';
    document.getElementById('calendar').innerHTML = html;
}

function resolveShowLabel(entry) {
    if (entry.show_id) {
        const s = SHOWS.find(x => x.id === entry.show_id);
        return s ? s.name : entry.show_id;
    }
    const ids = entry.rotation_ids || [];
    return '↻ ' + ids.map(id => { const s = SHOWS.find(x => x.id === id); return s ? s.name : id; }).join(' / ');
}

function prevMonth() {
    curMonth--; if (curMonth < 1) { curMonth = 12; curYear--; }
    loadMonth(curYear, curMonth);
}
function nextMonth() {
    curMonth++; if (curMonth > 12) { curMonth = 1; curYear++; }
    loadMonth(curYear, curMonth);
}

// ---------------------------------------------------------------------------
// Day modal
// ---------------------------------------------------------------------------

function openDay(dateStr) {
    modalDate = dateStr;
    document.getElementById('modal-title').textContent = formatDate(dateStr);
    document.getElementById('day-modal').style.display = 'flex';
    renderModalEntries();
    updateBlackoutBtn();
}

function closeModal() {
    document.getElementById('day-modal').style.display = 'none';
    modalDate = null;
}

function formatDate(d) {
    return new Date(d + 'T12:00:00').toLocaleDateString('default', {weekday:'long',year:'numeric',month:'long',day:'numeric'});
}

function renderModalEntries() {
    const entries = (calData[modalDate] || []).filter(e => e.type === 'show');
    const div = document.getElementById('modal-entries');
    if (!entries.length) { div.innerHTML = '<p style="color:#888;">No shows scheduled.</p>'; return; }
    div.innerHTML = entries.map(e => `
      <div style="display:flex;align-items:center;gap:8px;margin-bottom:6px;background:#333;padding:6px 10px;border-radius:4px;">
        <span>${e.time} — ${resolveShowLabel(e)}</span>
        <button class="buttons" style="margin-left:auto;padding:2px 8px;" onclick="deleteEntry('${e.id}')">✕</button>
      </div>`).join('');
}

function updateBlackoutBtn() {
    const isBlackout = (calData[modalDate] || []).some(e => e.type === 'blackout');
    document.getElementById('blackout-status').textContent = isBlackout
        ? '⛔ This day is a blackout — no shows will fire.'
        : 'No blackout set for this day.';
    document.getElementById('blackout-btn').textContent = isBlackout ? 'Remove Blackout' : 'Mark as Blackout';
}

function toggleAssignType() {
    const type = document.getElementById('new-assign-type').value;
    document.getElementById('assign-specific').style.display = type === 'specific' ? '' : 'none';
    document.getElementById('assign-rotation').style.display = type === 'rotation'  ? '' : 'none';
}

async function addShow() {
    const time   = document.getElementById('new-time').value || '19:00';
    const assign = document.getElementById('new-assign-type').value;
    let body = { date: modalDate, type: 'show', time };

    if (assign === 'specific') {
        body.show_id = document.getElementById('new-show-id').value;
        if (!body.show_id) { alert('Please select a show.'); return; }
    } else {
        body.rotation_ids = [...document.querySelectorAll('.rotation-cb:checked')].map(cb => cb.value);
        if (body.rotation_ids.length < 2) { alert('Select at least 2 shows for a rotation.'); return; }
    }

    const res = await fetch(`${AJAX_BASE}&action=save_entry`, {
        method: 'POST', body: JSON.stringify(body), headers: {'Content-Type':'application/json'}
    });
    const data = await res.json();
    if (!calData[modalDate]) calData[modalDate] = [];
    calData[modalDate].push(data.entry);
    renderModalEntries();
    renderCalendar();
}

async function deleteEntry(id) {
    await fetch(`${AJAX_BASE}&action=delete_entry&id=${id}`);
    calData[modalDate] = (calData[modalDate] || []).filter(e => e.id !== id);
    renderModalEntries();
    renderCalendar();
}

async function toggleBlackout() {
    const existing = (calData[modalDate] || []).find(e => e.type === 'blackout');
    if (existing) {
        await fetch(`${AJAX_BASE}&action=delete_entry&id=${existing.id}`);
        calData[modalDate] = (calData[modalDate] || []).filter(e => e.id !== existing.id);
    } else {
        const res = await fetch(`${AJAX_BASE}&action=save_entry`, {
            method: 'POST',
            body: JSON.stringify({ date: modalDate, type: 'blackout', reason: '' }),
            headers: {'Content-Type':'application/json'}
        });
        const data = await res.json();
        if (!calData[modalDate]) calData[modalDate] = [];
        calData[modalDate].push(data.entry);
    }
    updateBlackoutBtn();
    renderCalendar();
}

// Close modal on backdrop click
document.getElementById('day-modal').addEventListener('click', e => {
    if (e.target === e.currentTarget) closeModal();
});

// Initial load
loadMonth(curYear, curMonth);
</script>

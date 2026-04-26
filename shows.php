<?php
$pluginName = "ShowManager";
$showsFile  = $settings['configDirectory'] . "/ShowManagerShows.config";

// ---- FPP playlist list (for dropdowns) ----
$playlists = [];
$playlistDir = "/home/fpp/media/playlists";
if (is_dir($playlistDir)) {
    foreach (glob("$playlistDir/*.json") as $f) {
        $playlists[] = basename($f, '.json');
    }
    sort($playlists);
}

// ---- Save ----
if (isset($_POST['save_shows'])) {
    $shows = [];
    $ids   = $_POST['show_id']   ?? [];
    $names = $_POST['show_name'] ?? [];
    $plsts = $_POST['show_playlist'] ?? [];
    $trans = $_POST['show_transition_playlist'] ?? [];
    $tsecs = $_POST['show_transition_secs'] ?? [];
    $dmins = $_POST['show_duration_mins'] ?? [];

    foreach ($ids as $i => $id) {
        $id = preg_replace('/[^a-z0-9_]/', '_', strtolower(trim($id)));
        if ($id === '' || ($plsts[$i] ?? '') === '') continue;
        $shows[] = [
            'id'                    => $id,
            'name'                  => trim($names[$i] ?? $id),
            'playlist'              => trim($plsts[$i]),
            'transition_playlist'   => trim($trans[$i] ?? ''),
            'transition_secs'       => max(0, (int)($tsecs[$i] ?? 120)),
            'duration_mins'         => max(1, (int)($dmins[$i] ?? 15)),
        ];
    }

    file_put_contents($showsFile, json_encode(['shows' => $shows], JSON_PRETTY_PRINT));
    echo '<div class="alert alert-success">Shows saved.</div>';
}

// ---- Load ----
$data  = file_exists($showsFile) ? json_decode(file_get_contents($showsFile), true) : [];
$shows = $data['shows'] ?? [];

// Ensure at least one blank row
if (empty($shows)) {
    $shows = [['id'=>'','name'=>'','playlist'=>'','transition_playlist'=>'','transition_secs'=>120,'duration_mins'=>15]];
}

function pl_select($name, $selected, $playlists, $allow_blank = true) {
    $html = "<select name=\"$name\" class=\"fpp-select\">";
    if ($allow_blank) $html .= '<option value="">— none —</option>';
    foreach ($playlists as $p) {
        $sel   = ($p === $selected) ? ' selected' : '';
        $label = htmlspecialchars($p);
        $html .= "<option value=\"$label\"$sel>$label</option>";
    }
    $html .= '</select>';
    return $html;
}
?>

<h2>Show Definitions</h2>
<p>Define each show playlist here. The schedule page uses these to build your calendar.</p>

<form method="post">
<table class="fpp-settings-table" id="shows-table">
  <thead>
    <tr>
      <th>ID&nbsp;<small>(no spaces)</small></th>
      <th>Display Name</th>
      <th>FPP Playlist</th>
      <th>Pre-Show Transition Playlist&nbsp;<small>(optional — for dimming)</small></th>
      <th>Transition Duration&nbsp;<small>(secs)</small></th>
      <th>Approx. Duration&nbsp;<small>(mins)</small></th>
      <th></th>
    </tr>
  </thead>
  <tbody>
  <?php foreach ($shows as $s): ?>
  <tr>
    <td><input type="text" name="show_id[]"    value="<?= htmlspecialchars($s['id'])   ?>" size="12" pattern="[a-zA-Z0-9_]+"></td>
    <td><input type="text" name="show_name[]"  value="<?= htmlspecialchars($s['name']) ?>" size="16"></td>
    <td><?= pl_select('show_playlist[]',            $s['playlist'],            $playlists, false) ?></td>
    <td><?= pl_select('show_transition_playlist[]', $s['transition_playlist'], $playlists, true)  ?></td>
    <td><input type="number" name="show_transition_secs[]" value="<?= (int)$s['transition_secs'] ?>" min="0" max="600" size="5"></td>
    <td><input type="number" name="show_duration_mins[]"   value="<?= (int)$s['duration_mins']   ?>" min="1" max="180" size="5"></td>
    <td><button type="button" class="buttons" onclick="removeRow(this)">Remove</button></td>
  </tr>
  <?php endforeach; ?>
  </tbody>
</table>

<br>
<button type="button" class="buttons" onclick="addRow()">+ Add Show</button>
&nbsp;
<input type="submit" name="save_shows" value="Save Shows" class="buttons">
</form>

<script>
const playlists = <?= json_encode($playlists) ?>;

function plSelect(name, required) {
    let s = `<select name="${name}" class="fpp-select">`;
    if (!required) s += '<option value="">— none —</option>';
    playlists.forEach(p => { s += `<option value="${p}">${p}</option>`; });
    s += '</select>';
    return s;
}

function addRow() {
    const tbody = document.querySelector('#shows-table tbody');
    const tr = document.createElement('tr');
    tr.innerHTML = `
      <td><input type="text" name="show_id[]" size="12" pattern="[a-zA-Z0-9_]+"></td>
      <td><input type="text" name="show_name[]" size="16"></td>
      <td>${plSelect('show_playlist[]', true)}</td>
      <td>${plSelect('show_transition_playlist[]', false)}</td>
      <td><input type="number" name="show_transition_secs[]" value="120" min="0" max="600" size="5"></td>
      <td><input type="number" name="show_duration_mins[]" value="15" min="1" max="180" size="5"></td>
      <td><button type="button" class="buttons" onclick="removeRow(this)">Remove</button></td>
    `;
    tbody.appendChild(tr);
}

function removeRow(btn) {
    btn.closest('tr').remove();
}
</script>

<?php
$announceFile = $settings['configDirectory'] . "/ShowManagerAnnouncements.config";
$hwFile       = $settings['configDirectory'] . "/ShowManager.config";
$announceDir  = __DIR__ . "/announcements";

// ---- File upload ----
if (isset($_POST['do_upload']) && !empty($_FILES['upload_file']['name'])) {
    $f    = $_FILES['upload_file'];
    $ext  = strtolower(pathinfo($f['name'], PATHINFO_EXTENSION));
    $dest = ($_POST['upload_folder'] === 'daytime') ? $announceDir . '/daytime' : $announceDir;
    @mkdir($dest, 0755, true);
    if (in_array($ext, ['mp3', 'wav', 'ogg']) && $f['error'] === UPLOAD_ERR_OK) {
        move_uploaded_file($f['tmp_name'], $dest . '/' . basename($f['name']));
        echo '<div class="alert alert-success">File uploaded.</div>';
    } else {
        echo '<div class="alert alert-danger">Upload failed — only MP3, WAV, and OGG are accepted.</div>';
    }
}

// ---- File delete ----
if (isset($_POST['do_delete'])) {
    $target = realpath($announceDir . '/' . ltrim($_POST['do_delete'], '/'));
    if ($target && str_starts_with($target, realpath($announceDir) . DIRECTORY_SEPARATOR)) {
        unlink($target);
    }
}

// ---- FPP playlist list ----
$playlists = [];
$playlistDir = "/home/fpp/media/playlists";
if (is_dir($playlistDir)) {
    foreach (glob("$playlistDir/*.json") as $f) $playlists[] = basename($f, '.json');
    sort($playlists);
}

// ---- Save ----
if (isset($_POST['save_announcements'])) {
    $hw = file_exists($hwFile) ? json_decode(file_get_contents($hwFile), true) : [];
    $hw['duck_level']      = (float)($_POST['duck_level']      ?? 0.25);
    $hw['duck_fade_secs']  = (float)($_POST['duck_fade_secs']  ?? 2.0);
    file_put_contents($hwFile, json_encode($hw, JSON_PRETTY_PRINT));

    $preShow = [];
    $offsets = $_POST['pre_offset'] ?? [];
    $files   = $_POST['pre_file']   ?? [];
    foreach ($offsets as $i => $offset) {
        $offset = (float)$offset;
        $file   = trim($files[$i] ?? '');
        if ($offset > 0 && $file !== '') {
            $preShow[] = ['mins_before' => $offset, 'file' => $file];
        }
    }
    usort($preShow, fn($a,$b) => $b['mins_before'] <=> $a['mins_before']); // descending

    $an = [
        'folder'               => $announceDir,
        'gain_db'              => (float)($_POST['gain_db']             ?? 6.0),
        'max_duration_secs'    => (int)  ($_POST['max_duration_secs']   ?? 300),
        'background_playlist'  => trim(  $_POST['background_playlist']  ?? ''),
        'pre_show_brightness'  => (int)  ($_POST['pre_show_brightness'] ?? 20),
        'normal_brightness'    => (int)  ($_POST['normal_brightness']   ?? 100),
        'pre_show'             => $preShow,
        'daytime'              => [
            'enabled'       => isset($_POST['daytime_enabled']),
            'start'         => $_POST['daytime_start']    ?? '10:00',
            'end'           => $_POST['daytime_end']      ?? '18:00',
            'interval_mins' => (int)($_POST['daytime_interval'] ?? 20),
            'folder'        => rtrim($announceDir, '/') . '/daytime',
        ],
    ];
    file_put_contents($announceFile, json_encode($an, JSON_PRETTY_PRINT));

    // Create announcement folders if missing
    @mkdir($announceDir . '/daytime', 0755, true);

    echo '<div class="alert alert-success">Settings saved.</div>';
}

// ---- Load ----
$hw = file_exists($hwFile)       ? json_decode(file_get_contents($hwFile),       true) : [];
$an = file_exists($announceFile) ? json_decode(file_get_contents($announceFile), true) : [];

$duck_level     = $hw['duck_level']     ?? 0.25;
$duck_fade      = $hw['duck_fade_secs'] ?? 2.0;
$gain_db        = $an['gain_db']        ?? 6.0;
$max_dur        = $an['max_duration_secs'] ?? 300;
$bg_playlist    = $an['background_playlist'] ?? '';
$pre_bright     = $an['pre_show_brightness'] ?? 20;
$norm_bright    = $an['normal_brightness']   ?? 100;
$pre_show       = $an['pre_show'] ?? [];
$daytime        = $an['daytime']  ?? [];

// Ensure at least one blank pre-show row
if (empty($pre_show)) $pre_show = [['mins_before'=>5,'file'=>'']];

// Available MP3s in announcements folder
$mp3s = [];
if (is_dir($announceDir)) {
    foreach (glob("$announceDir/*.mp3") as $f) $mp3s[] = basename($f);
}

function pl_option($name, $selected, $playlists) {
    $html = "<select name=\"$name\" class=\"fpp-select\"><option value=\"\">— none —</option>";
    foreach ($playlists as $p) {
        $s = ($p === $selected) ? ' selected' : '';
        $html .= "<option value=\"" . htmlspecialchars($p) . "\"$s>" . htmlspecialchars($p) . "</option>";
    }
    return $html . '</select>';
}
?>

<form method="post">

<h3>Ducking</h3>
<table class="fpp-settings-table">
  <tr>
    <td>Music duck level during announcement</td>
    <td><input type="number" name="duck_level" value="<?= $duck_level ?>" min="0" max="1" step="0.05" size="6"></td>
    <td>Fader level 0.0–1.0 (e.g. 0.25 = 25% of normal)</td>
  </tr>
  <tr>
    <td>Fade duration</td>
    <td><input type="number" name="duck_fade_secs" value="<?= $duck_fade ?>" min="0.5" max="10" step="0.5" size="6"></td>
    <td>Seconds to fade down/up around each announcement</td>
  </tr>
  <tr>
    <td>Announcement gain boost</td>
    <td><input type="number" name="gain_db" value="<?= $gain_db ?>" min="0" max="24" step="1" size="6"> dB</td>
    <td>Software volume boost applied to announcement audio (6 dB ≈ 2× louder)</td>
  </tr>
  <tr>
    <td>Max announcement duration</td>
    <td><input type="number" name="max_duration_secs" value="<?= $max_dur ?>" min="10" max="3600" size="6"> sec</td>
    <td>Safety timeout — playback is killed if it runs longer than this</td>
  </tr>
</table>

<h3>Lighting (fpp-brightness)</h3>
<table class="fpp-settings-table">
  <tr>
    <td>Pre-show brightness</td>
    <td><input type="number" name="pre_show_brightness" value="<?= $pre_bright ?>" min="0" max="200" size="6"></td>
    <td>Brightness level set before show starts (0–200, 100 = normal, 0 = off)</td>
  </tr>
  <tr>
    <td>Normal brightness</td>
    <td><input type="number" name="normal_brightness" value="<?= $norm_bright ?>" min="0" max="200" size="6"></td>
    <td>Brightness restored after show ends</td>
  </tr>
</table>

<h3>Background Music</h3>
<table class="fpp-settings-table">
  <tr>
    <td>Background playlist</td>
    <td><?= pl_option('background_playlist', $bg_playlist, $playlists) ?></td>
    <td>Auto-started (looping) after each show ends</td>
  </tr>
</table>

<h3>Pre-Show Announcements</h3>
<p>Audio files should be placed in <code><?= htmlspecialchars($announceDir) ?></code>.<br>
Each row fires one announcement the specified number of minutes before the show.</p>

<table class="fpp-settings-table" id="pre-table">
  <thead>
    <tr><th>Minutes before show</th><th>Audio file</th><th></th></tr>
  </thead>
  <tbody>
  <?php foreach ($pre_show as $p): ?>
  <tr>
    <td><input type="number" name="pre_offset[]" value="<?= (float)$p['mins_before'] ?>" min="1" max="120" step="1" size="6"></td>
    <td>
      <input type="text" name="pre_file[]" value="<?= htmlspecialchars($p['file']) ?>" size="28" list="mp3-list">
    </td>
    <td><button type="button" class="buttons" onclick="this.closest('tr').remove()">Remove</button></td>
  </tr>
  <?php endforeach; ?>
  </tbody>
</table>

<datalist id="mp3-list">
  <?php foreach ($mp3s as $f): ?>
  <option value="<?= htmlspecialchars($f) ?>">
  <?php endforeach; ?>
</datalist>

<button type="button" class="buttons" onclick="addPreRow()">+ Add Announcement</button>

<h3>Daytime General Announcements</h3>
<p>Plays a random MP3 from <code><?= htmlspecialchars($announceDir) ?>/daytime/</code> on a repeating interval.
   Automatically suppressed when a show is running or within 20 minutes of a show start.</p>

<table class="fpp-settings-table">
  <tr>
    <td>Enable daytime announcements</td>
    <td><input type="checkbox" name="daytime_enabled" <?= !empty($daytime['enabled']) ? 'checked' : '' ?>></td>
  </tr>
  <tr>
    <td>Active window</td>
    <td>
      <input type="time" name="daytime_start" value="<?= htmlspecialchars($daytime['start'] ?? '10:00') ?>">
      &nbsp;to&nbsp;
      <input type="time" name="daytime_end"   value="<?= htmlspecialchars($daytime['end']   ?? '18:00') ?>">
    </td>
  </tr>
  <tr>
    <td>Interval between announcements</td>
    <td><input type="number" name="daytime_interval" value="<?= (int)($daytime['interval_mins'] ?? 20) ?>" min="5" max="240" size="5"> minutes</td>
  </tr>
</table>

<br>
<input type="submit" name="save_announcements" value="Save Settings" class="buttons">
</form>

<hr style="margin:28px 0;">

<h3>Announcement Files</h3>

<form method="post" enctype="multipart/form-data">
<table class="fpp-settings-table">
  <tr>
    <td>File</td>
    <td><input type="file" name="upload_file" accept=".mp3,.wav,.ogg"></td>
    <td>MP3, WAV, or OGG</td>
  </tr>
  <tr>
    <td>Destination</td>
    <td>
      <select name="upload_folder" class="fpp-select">
        <option value="main">Main (pre-show announcements)</option>
        <option value="daytime">Daytime</option>
      </select>
    </td>
    <td></td>
  </tr>
</table>
<br>
<input type="submit" name="do_upload" value="Upload File" class="buttons">
</form>

<?php
$allFiles = [];
foreach (glob($announceDir . '/*.{mp3,wav,ogg}', GLOB_BRACE) as $f)
    $allFiles[] = ['name' => basename($f), 'path' => basename($f), 'folder' => 'Main'];
foreach (glob($announceDir . '/daytime/*.{mp3,wav,ogg}', GLOB_BRACE) as $f)
    $allFiles[] = ['name' => basename($f), 'path' => 'daytime/' . basename($f), 'folder' => 'Daytime'];
?>

<?php if ($allFiles): ?>
<table class="fpp-settings-table" style="margin-top:16px;">
  <thead><tr><th>File</th><th>Folder</th><th></th></tr></thead>
  <tbody>
  <?php foreach ($allFiles as $f): ?>
  <tr>
    <td><?= htmlspecialchars($f['name']) ?></td>
    <td><?= $f['folder'] ?></td>
    <td>
      <form method="post" style="display:inline;">
        <input type="hidden" name="do_delete" value="<?= htmlspecialchars($f['path']) ?>">
        <button type="submit" class="buttons" onclick="return confirm('Delete <?= htmlspecialchars($f['name']) ?>?')">Delete</button>
      </form>
    </td>
  </tr>
  <?php endforeach; ?>
  </tbody>
</table>
<?php else: ?>
<p style="color:#888;margin-top:12px;">No announcement files uploaded yet.</p>
<?php endif; ?>

<script>
function addPreRow() {
    const tbody = document.querySelector('#pre-table tbody');
    const tr = document.createElement('tr');
    tr.innerHTML = `
      <td><input type="number" name="pre_offset[]" value="5" min="1" max="120" step="1" size="6"></td>
      <td><input type="text" name="pre_file[]" size="28" list="mp3-list"></td>
      <td><button type="button" class="buttons" onclick="this.closest('tr').remove()">Remove</button></td>`;
    tbody.appendChild(tr);
}
</script>

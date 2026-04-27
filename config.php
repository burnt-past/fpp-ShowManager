<?php
$configFile = $settings['configDirectory'] . "/ShowManager.config";

if (isset($_POST['submit'])) {
    $cfg = [
        'xr18_ip'      => trim($_POST['xr18_ip']),
        'music_ch1'    => trim($_POST['music_ch1']),
        'music_ch2'    => trim($_POST['music_ch2']),
        'announce_ch'  => trim($_POST['announce_ch']),
        'announce_vol' => trim($_POST['announce_vol']),
    ];
    file_put_contents($configFile, json_encode($cfg, JSON_PRETTY_PRINT));
    echo "<div class='alert alert-success'>Settings saved. Restart the plugin for changes to take effect.</div>";
}

$cfg = [];
if (file_exists($configFile)) {
    $cfg = json_decode(file_get_contents($configFile), true) ?: [];
}

$xr18_ip     = htmlspecialchars($cfg['xr18_ip']      ?? '192.168.0.1');
$music_ch1   = htmlspecialchars($cfg['music_ch1']    ?? '01');
$music_ch2   = htmlspecialchars($cfg['music_ch2']    ?? '02');
$announce_ch = htmlspecialchars($cfg['announce_ch']  ?? '03');
$announce_vol = htmlspecialchars($cfg['announce_vol'] ?? '0.75');
?>

<p>The bridge syncs FPP master volume to the two music channels on the XR18 via OSC (UDP port 10024).
   Moving either music-channel fader on the XR18 also updates FPP volume.
   The announcement channel is held at its own independent level and is not affected by FPP volume changes.</p>

<form method="post">
<table class="fpp-settings-table">
  <tr>
    <td><b>XR18 IP Address</b></td>
    <td><input type="text" name="xr18_ip" value="<?= $xr18_ip ?>" size="20"></td>
    <td>IP of the XR18 on your network (check X AIR Edit)</td>
  </tr>
  <tr>
    <td><b>Music Channel 1</b></td>
    <td><input type="text" name="music_ch1" value="<?= $music_ch1 ?>" size="4"></td>
    <td>XR18 channel number for left/mono music (01–18)</td>
  </tr>
  <tr>
    <td><b>Music Channel 2</b></td>
    <td><input type="text" name="music_ch2" value="<?= $music_ch2 ?>" size="4"></td>
    <td>XR18 channel number for right music (01–18)</td>
  </tr>
  <tr>
    <td><b>Announcement Channel</b></td>
    <td><input type="text" name="announce_ch" value="<?= $announce_ch ?>" size="4"></td>
    <td>XR18 channel for pre-show announcements (01–18, must differ from music channels)</td>
  </tr>
  <tr>
    <td><b>Announcement Volume</b></td>
    <td><input type="text" name="announce_vol" value="<?= $announce_vol ?>" size="6"></td>
    <td>Fader level for announcement channel: 0.0 (off) – 1.0 (full). 0.75 ≈ unity.</td>
  </tr>
</table>
<br>
<input type="submit" name="submit" value="Save Settings" class="buttons">
</form>

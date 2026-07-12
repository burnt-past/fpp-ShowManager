<?php
$pluginDir = __DIR__;

chmod("$pluginDir/Scripts/xr18_bridge.py",    0755);
chmod("$pluginDir/Scripts/show_scheduler.py", 0755);
chmod("$pluginDir/scripts/postStart.sh",      0755);

// Ensure announcement folders exist
@mkdir("$pluginDir/announcements/daytime", 0755, true);

// Restart daemons only when explicitly requested — not on every page view
if (isset($_POST['restart_daemons'])) {
    exec("pkill -f xr18_bridge.py    2>/dev/null");
    exec("pkill -f show_scheduler.py 2>/dev/null");
    sleep(1);
    exec("python3 " . escapeshellarg("$pluginDir/Scripts/xr18_bridge.py")    . " >> /home/fpp/media/logs/xr18_bridge.log 2>&1 &");
    exec("python3 " . escapeshellarg("$pluginDir/Scripts/show_scheduler.py") . " >> /home/fpp/media/logs/showmanager.log 2>&1 &");
    echo "<div class='alert alert-success'>Daemons restarted.</div>";
}

$bridgeRunning    = (int)shell_exec('pgrep -fc xr18_bridge.py 2>/dev/null') > 0;
$schedulerRunning = (int)shell_exec('pgrep -fc show_scheduler.py 2>/dev/null') > 0;
?>
<p>XR18 bridge: <b><?= $bridgeRunning ? 'running' : 'stopped' ?></b><br>
   Show scheduler: <b><?= $schedulerRunning ? 'running' : 'stopped' ?></b></p>
<p>Both daemons start automatically when FPP boots (via <code>scripts/postStart.sh</code>).</p>
<form method="post">
  <input type="submit" name="restart_daemons" value="Restart Daemons" class="buttons">
</form>

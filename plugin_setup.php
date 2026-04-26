<?php
$pluginDir  = __DIR__;

$bridge    = "$pluginDir/Scripts/xr18_bridge.py";
$scheduler = "$pluginDir/Scripts/show_scheduler.py";

chmod($bridge,    0755);
chmod($scheduler, 0755);

// Ensure announcement folders exist
@mkdir("$pluginDir/announcements/daytime", 0755, true);

// Restart both daemons cleanly
exec("pkill -f xr18_bridge.py    2>/dev/null");
exec("pkill -f show_scheduler.py 2>/dev/null");
sleep(1);

exec("python3 $bridge    >> /home/fpp/media/logs/xr18_bridge.log    2>&1 &");
exec("python3 $scheduler >> /home/fpp/media/logs/showmanager.log 2>&1 &");
?>

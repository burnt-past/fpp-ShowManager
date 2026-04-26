<?php
$pluginName = "XR18VolumeControl";
$bridge     = "/home/fpp/media/plugins/$pluginName/Scripts/xr18_bridge.py";

chmod($bridge, 0755);

// Kill any stale instance before (re)starting
exec("pkill -f xr18_bridge.py 2>/dev/null");
sleep(1);

exec("python3 $bridge >> /home/fpp/media/logs/xr18_bridge.log 2>&1 &");

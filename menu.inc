<?php
$pluginDir  = $settings['pluginDirectory'] . "/XR18VolumeControl";
$pluginJson = "$pluginDir/pluginInfo.json";
$pluginData = json_decode(file_get_contents($pluginJson), true);
$pluginBase = $pluginData['name'];

$menuItems = [];
$menuItems["$pluginBase - Hardware"]      = "$pluginDir/config.php";
$menuItems["$pluginBase - Shows"]         = "$pluginDir/shows.php";
$menuItems["$pluginBase - Schedule"]      = "$pluginDir/schedule.php";
$menuItems["$pluginBase - Announcements"] = "$pluginDir/announcements.php";
?>

<?php
$pluginDirName = basename(__DIR__);
$pluginData    = json_decode(file_get_contents(__DIR__ . "/pluginInfo.json"), true);
$pluginBase    = $pluginData['name'];

$menuItems["$pluginBase - Hardware"]      = "plugin.php?plugin=$pluginDirName&page=config.php";
$menuItems["$pluginBase - Shows"]         = "plugin.php?plugin=$pluginDirName&page=shows.php";
$menuItems["$pluginBase - Schedule"]      = "plugin.php?plugin=$pluginDirName&page=schedule.php";
$menuItems["$pluginBase - Announcements"] = "plugin.php?plugin=$pluginDirName&page=announcements.php";
?>

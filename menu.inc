<?php

$menuEntries = Array(
	Array('text' => 'Show Manager - Hardware',      'type' => 'status', 'page' => 'config.php',        'wrap' => 1),
	Array('text' => 'Show Manager - Shows',         'type' => 'status', 'page' => 'shows.php',         'wrap' => 1),
	Array('text' => 'Show Manager - Schedule',      'type' => 'status', 'page' => 'schedule.php',      'wrap' => 1),
	Array('text' => 'Show Manager - Announcements', 'type' => 'status', 'page' => 'announcements.php', 'wrap' => 1),
);

foreach ($menuEntries as $entry)
{
	if ($entry['type'] != $menu)
		continue;

	printf("<li><a href='plugin.php?plugin=%s&page=%s'>%s</a></li>\n",
		$plugin, $entry['page'], $entry['text']);
}
?>

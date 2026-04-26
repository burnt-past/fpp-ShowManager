<?php
$tab = $_GET['tab'] ?? 'hardware';

// AJAX passthrough — schedule.php outputs JSON and exits
if ($tab === 'schedule' && isset($_GET['action'])) {
    include __DIR__ . '/schedule.php';
    exit;
}

$base  = "plugin.php?plugin={$plugin}&page=index.php";
$tabs  = ['hardware' => 'Hardware', 'shows' => 'Shows', 'schedule' => 'Schedule', 'announcements' => 'Announcements'];
$files = ['hardware' => 'config.php', 'shows' => 'shows.php', 'schedule' => 'schedule.php', 'announcements' => 'announcements.php'];

ob_start();
include __DIR__ . '/' . ($files[$tab] ?? 'config.php');
$content = ob_get_clean();
?>
<h2>Show Manager</h2>

<ul class="nav nav-tabs" style="margin-bottom:20px;">
<?php foreach ($tabs as $key => $label): ?>
  <li class="nav-item">
    <a class="nav-link<?= $tab === $key ? ' active' : '' ?>"
       href="<?= $base ?>&tab=<?= $key ?>"><?= htmlspecialchars($label) ?></a>
  </li>
<?php endforeach; ?>
</ul>

<div style="padding-top:4px;">
<?= $content ?>
</div>

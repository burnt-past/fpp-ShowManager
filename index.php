<?php
$tab = $_GET['tab'] ?? 'status';

$base  = "plugin.php?plugin=" . basename(__DIR__) . "&page=index.php";
$tabs  = ['status' => 'Status', 'schedule' => 'Schedule', 'announcements' => 'Announcements', 'hardware' => 'Hardware'];
$files = ['status' => 'status.php', 'schedule' => 'schedule.php', 'announcements' => 'announcements.php', 'hardware' => 'config.php'];

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

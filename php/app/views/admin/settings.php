<?php // Редактор работает в браузере: assets/js/admin-settings.js ?>
<script type="application/json" id="admin-data"><?= admin_json($editor) ?></script>
<div id="admin-app" class="stack" style="gap: 20px"></div>
<?php
// Резервная копия: делает cron каждую ночь после 3:00 (Backup, Maintenance)
$when = fn (?string $at) => $at ? date('d.m.Y в H:i', strtotime($at)) : 'ещё не было';
?>
<div class="card stack" style="gap: 6px; margin-top: 20px">
  <b>Резервная копия базы и фото</b>
  <?php if (!$backup): ?>
    <span class="small muted">Ещё не делалась. Копию каждую ночь делает задание по расписанию (cron), вручную — <code>php app/cli.php backup</code>.</span>
  <?php elseif ($backup['ok'] ?? false): ?>
    <span class="small">Последняя: <?= e($when($backup['at'])) ?>, восстановление проверено ✓</span>
    <span class="small muted">Хранятся <?= Backup::KEEP_DAYS ?> дней в папке <?= e(dirname($backup['file'])) ?> (вне сайта).</span>
  <?php else: ?>
    <span class="error">Последняя копия не получилась (<?= e($when($backup['at'])) ?>): <?= e($backup['error'] ?? '') ?></span>
    <span class="small muted">Последняя удачная: <?= e($when($backup['lastOk'] ?? null)) ?></span>
  <?php endif ?>
</div>

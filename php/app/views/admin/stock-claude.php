<?php
// «Финансы» → «Скопировать для Claude»: все цифры за период одним блоком. Маршрут — routes/admin-stock.php (/admin/stock/claude).
$period = ['p' => $preset, 'from' => $preset === 'custom' ? $from : null, 'to' => $preset === 'custom' ? $to : null];
$q = fn (array $ch) => '/admin/stock/claude?' . http_build_query(array_filter(array_merge($period, $ch), fn ($v) => $v !== null && $v !== ''));
[$nPur, $nWo, $nBank] = $counts;
?>
<a class="small" href="/admin/stock?<?= e(http_build_query(array_filter($period))) ?>">← Финансы</a>
<h1>Скопировать для Claude</h1>
<span class="muted">Здесь все цифры «Финансов» за период одним блоком: накладные, списания, записи банка, итоги по магазинам и сверка с поставщиками. Нажмите «Скопировать», вставьте в чат с Claude и спросите, что нужно: сверить, найти ошибки, сравнить месяцы.</span>

<div class="tabs">
  <?php foreach (['month' => 'Этот месяц', 'prev' => 'Прошлый месяц', 'year' => 'Год'] as $k => $label): ?>
    <a class="chip chip-sm <?= $preset === $k ? 'on' : '' ?>" href="<?= e($q(['p' => $k, 'from' => null, 'to' => null])) ?>"><?= e($label) ?></a>
  <?php endforeach ?>
  <form class="row" style="gap: 8px" method="get" action="/admin/stock/claude">
    <input type="hidden" name="p" value="custom">
    <input class="input" type="date" name="from" value="<?= e($from) ?>" aria-label="С" style="width: auto; padding: 8px 10px">
    <span class="muted">–</span>
    <input class="input" type="date" name="to" value="<?= e($to) ?>" aria-label="По" style="width: auto; padding: 8px 10px">
    <button class="chip chip-sm <?= $preset === 'custom' ? 'on' : '' ?>">Показать</button>
  </form>
</div>

<div class="card stack" style="gap: 12px">
  <div class="row between">
    <b><?= e(admin_date($from)) ?> – <?= e(admin_date($to)) ?> <?= e(substr($to, 0, 4)) ?> · накладных <?= (int) $nPur ?>, списаний <?= (int) $nWo ?>, записей банка <?= (int) $nBank ?></b>
    <button class="btn btn-lg" type="button" id="cl-copy">⧉ Скопировать</button>
  </div>
  <textarea class="input" id="cl-text" rows="10" readonly style="font-family: monospace; font-size: 12px"><?= e($text) ?></textarea>
  <span class="small muted" id="cl-done" hidden>Скопировано. Вставьте в чат с Claude.</span>
</div>
<script>
  document.getElementById('cl-copy').addEventListener('click', function () {
    var t = document.getElementById('cl-text');
    var done = function () { document.getElementById('cl-done').hidden = false; };
    if (navigator.clipboard && window.isSecureContext) navigator.clipboard.writeText(t.value).then(done, function () { t.select(); document.execCommand('copy'); done(); });
    else { t.select(); document.execCommand('copy'); done(); }
  });
</script>

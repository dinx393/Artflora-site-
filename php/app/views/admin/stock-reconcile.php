<?php
// «Финансы» → сверка с поставщиками: накладные против оплат по банку. Маршрут — routes/admin-stock.php (/admin/stock/reconcile).
$period = ['p' => $preset, 'from' => $preset === 'custom' ? $from : null, 'to' => $preset === 'custom' ? $to : null];
$q = fn (array $ch) => '/admin/stock/reconcile?' . http_build_query(array_filter(array_merge($period, $ch), fn ($v) => $v !== null && $v !== ''));
$back = '/admin/stock?' . http_build_query(array_filter($period));
$money = fn (int $kop) => e(number_format(abs($kop) / 100, 2, ',', ' ')); // в сверке всегда с копейками, чтобы столбцы читались ровно
$periodText = admin_date($from) . ' – ' . admin_date($to) . ' ' . substr($to, 0, 4);
// Итог по поставщику словами: долг (мы должны), переплата, или сошлось
$state = function (int $kop) use ($money): string {
    if (abs($kop) < 100) return '<span class="pill ok">сходится</span>';
    return $kop > 0 ? '<span class="pill warn">должны ' . $money($kop) . '</span>' : '<span class="pill">переплата ' . $money($kop) . '</span>';
};
$tot = ['openKop' => 0, 'invKop' => 0, 'paidKop' => 0, 'closeKop' => 0];
foreach ($rows as $r) foreach ($tot as $k => $_) $tot[$k] += $r[$k];
?>
<style>
  .rc-table td.r, .rc-table th.r { text-align: right; font-variant-numeric: tabular-nums; white-space: nowrap; }
  .rc-table tr.total td { font-weight: 800; border-top: 2px solid var(--line); }
  .rc-in { color: var(--cherry); } .rc-pay { color: var(--olive); }
  details.rc-sup { border-bottom: 1px solid var(--line-soft); padding: 12px 0; }
  details.rc-sup summary { cursor: pointer; display: grid; grid-template-columns: 1.4fr repeat(4, 1fr) 150px; gap: 10px; align-items: baseline; font-variant-numeric: tabular-nums; }
  details.rc-sup summary .n { text-align: right; white-space: nowrap; }
  .rc-head { display: grid; grid-template-columns: 1.4fr repeat(4, 1fr) 150px; gap: 10px; font-size: 12px; text-transform: uppercase; letter-spacing: .05em; font-weight: 800; color: var(--muted); padding-bottom: 6px; border-bottom: 2px solid var(--line); }
  .rc-head span:not(:first-child) { text-align: right; }
  .rc-head span:last-child { text-align: left; }
  @media (max-width: 760px) {
    .rc-head { display: none; }
    details.rc-sup summary { grid-template-columns: 1fr 1fr; }
    details.rc-sup summary .n::before { content: attr(data-k) ' '; color: var(--muted); font-size: 12px; }
  }
</style>

<a class="small" href="<?= e($back) ?>">← Финансы</a>
<div class="stack" style="gap: 4px">
  <h1>Сверка с поставщиками</h1>
  <b><?= e($periodText) ?></b>
</div>
<span class="muted">Сколько поставщики выставили по накладным и сколько мы им заплатили по банку (расходы «Поставщики»). Оплату сайт относит к поставщику сам — по названию в начале описания («Аллюрфлор, док. 1611…» = «Аллюр»). <b>Должны</b> — накладных больше, чем оплат. <b>Переплата</b> — заплатили больше: это аванс или накладные, которые ещё не внесли (например, за прошлый месяц). «На начало» — всё, что было до первого дня периода.</span>

<div class="tabs">
  <?php foreach (['month' => 'Этот месяц', 'prev' => 'Прошлый месяц', 'year' => 'Год'] as $k => $label): ?>
    <a class="chip chip-sm <?= $preset === $k ? 'on' : '' ?>" href="<?= e($q(['p' => $k, 'from' => null, 'to' => null])) ?>"><?= e($label) ?></a>
  <?php endforeach ?>
  <form class="row" style="gap: 8px" method="get" action="/admin/stock/reconcile">
    <input type="hidden" name="p" value="custom">
    <input class="input" type="date" name="from" value="<?= e($from) ?>" aria-label="С" style="width: auto; padding: 8px 10px">
    <span class="muted">–</span>
    <input class="input" type="date" name="to" value="<?= e($to) ?>" aria-label="По" style="width: auto; padding: 8px 10px">
    <button class="chip chip-sm <?= $preset === 'custom' ? 'on' : '' ?>">Показать</button>
  </form>
</div>

<div class="card stack" style="gap: 0">
  <?php if (!$rows): ?>
    <p class="muted">За этот период нет ни накладных, ни оплат поставщикам.</p>
  <?php else: ?>
    <div class="rc-head"><span>Поставщик</span><span>На начало</span><span>Накладные</span><span>Оплачено</span><span>Итог</span><span></span></div>
    <?php foreach ($rows as $r): ?>
      <details class="rc-sup">
        <summary>
          <b><?= e($r['name']) ?></b>
          <span class="n" data-k="На начало"><?= $r['openKop'] ? ($r['openKop'] < 0 ? '−' : '') . $money($r['openKop']) : '—' ?></span>
          <span class="n rc-in" data-k="Накладные"><?= $r['invKop'] ? $money($r['invKop']) : '—' ?></span>
          <span class="n rc-pay" data-k="Оплачено"><?= $r['paidKop'] ? $money($r['paidKop']) : '—' ?></span>
          <span class="n" data-k="Итог"><b><?= ($r['closeKop'] < 0 ? '−' : '') . $money($r['closeKop']) ?></b></span>
          <span><?= $state($r['closeKop']) ?></span>
        </summary>
        <div class="table-wrap" style="margin-top: 10px"><table class="table rc-table">
          <thead><tr><th>Дата</th><th>Что</th><th class="r">Накладная</th><th class="r">Оплата</th><th class="r">Остаток</th></tr></thead>
          <tbody>
            <?php if ($r['openKop']): ?><tr><td></td><td class="muted">На начало периода</td><td></td><td></td><td class="r"><?= ($r['openKop'] < 0 ? '−' : '') . $money($r['openKop']) ?></td></tr><?php endif ?>
            <?php foreach ($r['events'] as $ev): $inv = $ev['type'] === 'inv'; ?>
              <tr>
                <td style="white-space: nowrap"><?= e(admin_date($ev['date'])) ?></td>
                <td class="small"><?= e($ev['text']) ?></td>
                <td class="r rc-in"><?= $inv ? $money($ev['kop']) : '' ?></td>
                <td class="r rc-pay"><?= $inv ? '' : $money($ev['kop']) ?></td>
                <td class="r"><?= ($ev['balanceKop'] < 0 ? '−' : '') . $money($ev['balanceKop']) ?></td>
              </tr>
            <?php endforeach ?>
          </tbody>
        </table></div>
      </details>
    <?php endforeach ?>
    <div class="rc-head" style="border: 0; border-top: 2px solid var(--line); padding-top: 10px; color: var(--ink); font-size: 14px; text-transform: none; letter-spacing: 0">
      <span>Всего</span>
      <span><?= ($tot['openKop'] < 0 ? '−' : '') . $money($tot['openKop']) ?></span>
      <span><?= $money($tot['invKop']) ?></span>
      <span><?= $money($tot['paidKop']) ?></span>
      <span><?= ($tot['closeKop'] < 0 ? '−' : '') . $money($tot['closeKop']) ?></span>
      <span><?= $state($tot['closeKop']) ?></span>
    </div>
  <?php endif ?>
</div>
<span class="small muted">Если платёж попал не к тому поставщику или отдельной строкой — исправьте начало описания в «Финансы → запись банка → ✎ Исправить», чтобы оно начиналось с названия поставщика.</span>

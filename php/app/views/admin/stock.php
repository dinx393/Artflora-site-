<?php
// «Закупка и списание»: итоги за период по магазинам и позициям, графики, накладные и списания. Маршрут — routes/admin-stock.php.
$t = $report['total'];
$shopName = array_column($shops, 'name', 'code');
$colors = ['#7e1f3c', '#9a86c4', '#c98a9d', '#5b4580'];
$shopColor = [];
foreach ($shops as $i => $s) $shopColor[$s['code']] = $colors[$i % count($colors)];
$q = fn (array $ch) => '/admin/stock?' . http_build_query(array_filter(array_merge(['p' => $preset, 'from' => $preset === 'custom' ? $from : null, 'to' => $preset === 'custom' ? $to : null, 'shop' => $shop], $ch), fn ($v) => $v !== null && $v !== ''));
$back = http_build_query(array_filter(['p' => $preset, 'from' => $preset === 'custom' ? $from : null, 'to' => $preset === 'custom' ? $to : null, 'shop' => $shop]));
$money = fn (int $kop) => e(preg_replace('/ BYN$/', '', Money::format($kop)));
$byn = fn (int $kop) => admin_byn($kop);
$periodText = $from === $to ? admin_date($from) . ' ' . substr($from, 0, 4) : admin_date($from) . ' – ' . admin_date($to) . ' ' . substr($to, 0, 4);
$msgs = ['purchase' => 'Накладная сохранена.', 'writeoff' => 'Списание сохранено.', 'deleted' => 'Удалено.'];
$monthNames = ['янв', 'фев', 'мар', 'апр', 'май', 'июн', 'июл', 'авг', 'сен', 'окт', 'ноя', 'дек'];
$itemName = fn (int $id) => $items[$id]['name'] ?? '?';

// Столбики по месяцам: $series = [[подпись, цвет, fn(месяц) → копейки], …] (масштаб — от самого большого значения)
$chart = function (array $series) use ($months, $monthNames): string {
    $max = 0;
    foreach ($months as $m) foreach ($series as [, , $get]) $max = max($max, $get($m));
    if ($max === 0) return '<p class="small muted">Пока нет данных.</p>';
    $stepRaw = $max / 4;
    $pow = 10 ** floor(log10(max(1, $stepRaw)));
    $step = (int) (ceil($stepRaw / $pow) * $pow);
    $top = $step * 4;
    [$L, $R, $T, $B] = [56, 560, 14, 196];
    $y = fn ($v) => round($B - $v / $top * ($B - $T), 1);
    $svg = '<svg viewBox="0 0 570 222" class="stk-chart" role="img">';
    for ($v = 0; $v <= $top; $v += $step) {
        $svg .= '<line x1="' . $L . '" x2="' . $R . '" y1="' . $y($v) . '" y2="' . $y($v) . '" stroke="#eedde2"/>';
        $svg .= '<text x="' . ($L - 6) . '" y="' . ($y($v) + 4) . '" text-anchor="end">' . number_format($v / 100, 0, ',', ' ') . '</text>';
    }
    $n = count($months);
    $k = count($series);
    $w = ($R - $L) / $n;
    $bw = min(28, $w * 0.7 / max(1, $k));
    $i = 0;
    foreach ($months as $ym => $m) {
        $x0 = $L + $i * $w + ($w - $bw * $k - 3 * ($k - 1)) / 2;
        foreach ($series as $j => [$label, $color, $get]) {
            $v = $get($m);
            $svg .= '<rect x="' . round($x0 + $j * ($bw + 3), 1) . '" y="' . $y($v) . '" width="' . round($bw, 1) . '" height="' . round($B - $y($v), 1) . '" rx="3" fill="' . $color . '"><title>' . e($label) . ': ' . e(Money::format($v)) . '</title></rect>';
        }
        $svg .= '<text x="' . round($L + $i * $w + $w / 2, 1) . '" y="' . ($B + 18) . '" text-anchor="middle">' . $monthNames[(int) substr($ym, 5, 2) - 1] . '</text>';
        $i++;
    }
    return $svg . '</svg>';
};
$byShop = fn (string $key) => array_map(fn ($s) => [$s['name'], $shopColor[$s['code']], fn ($m) => $m[$key][$s['code']] ?? 0], $shops);
$legend = fn (array $series) => '<div class="stk-legend">' . implode('', array_map(fn ($x) => '<span><i style="background: ' . $x[1] . '"></i>' . e($x[0]) . '</span>', $series)) . '</div>';
$bankSeries = [['Пришло', '#4f5a2a', fn ($m) => $m['bank']['IN']], ['Ушло', '#a3283c', fn ($m) => $m['bank']['OUT']]];
?>
<style>
  .stk-tiles { display: grid; grid-template-columns: repeat(auto-fit, minmax(190px, 1fr)); gap: 12px; }
  .stk-tile { border: 1px solid var(--line); border-radius: 16px; padding: 14px 16px; display: flex; flex-direction: column; gap: 2px; }
  .stk-tile .k { font-size: 12px; text-transform: uppercase; letter-spacing: .05em; font-weight: 800; color: var(--muted); }
  .stk-tile .v { font-size: 26px; font-weight: 800; color: var(--cherry); font-variant-numeric: tabular-nums; }
  .stk-tile .v .cur { font-size: 14px; margin-left: 4px; }
  .stk-tile.wo { background: #fbe6e9; border-color: transparent; }
  .stk-tile.wo .v { color: var(--danger); }
  .stk-tile.ok { background: #eef1e4; border-color: transparent; }
  .stk-tile.ok .v { color: var(--olive); }
  .stk-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); gap: 16px; }
  .stk-chart { width: 100%; height: auto; }
  .stk-chart text { font-size: 11px; fill: var(--muted); font-family: var(--sans); }
  .stk-legend { display: flex; gap: 14px; flex-wrap: wrap; font-size: 13px; color: var(--muted); }
  .stk-legend i { display: inline-block; width: 10px; height: 10px; border-radius: 3px; margin-right: 6px; }
  .stk-bars { display: flex; flex-direction: column; gap: 8px; }
  .stk-bar { display: grid; grid-template-columns: 150px 1fr 90px; gap: 10px; align-items: center; font-size: 14px; }
  .stk-bar .track { height: 10px; background: var(--blush); border-radius: 5px; overflow: hidden; }
  .stk-bar .fill { height: 100%; border-radius: 5px; }
  .stk-bar .n { text-align: right; font-variant-numeric: tabular-nums; font-weight: 700; }
  .stk-table td.r, .stk-table th.r { text-align: right; font-variant-numeric: tabular-nums; white-space: nowrap; }
  .stk-table tr.total td { font-weight: 800; border-top: 2px solid var(--line); }
  .stk-table .hi { color: var(--danger); font-weight: 800; }
  .stk-in { color: var(--olive); } .stk-out { color: var(--danger); }
  .stk-dates { display: flex; gap: 8px; align-items: center; flex-wrap: wrap; }
  .stk-dates input { width: auto; padding: 8px 10px; }
  details.stk-doc { border-bottom: 1px solid var(--line-soft); padding: 10px 0; }
  details.stk-doc summary { cursor: pointer; display: flex; gap: 12px; flex-wrap: wrap; align-items: baseline; }
  @media (max-width: 760px) { .stk-bar { grid-template-columns: 110px 1fr 80px; } }
</style>

<div class="row between">
  <h1>Финансы</h1>
  <div class="row">
    <a class="btn btn-pink" href="/admin/stock/money?dir=in">+ Доход</a>
    <a class="btn btn-pink" href="/admin/stock/money?dir=out">− Расход</a>
    <a class="btn" href="/admin/stock/purchase">+ Накладная</a>
    <a class="btn" href="/admin/stock/writeoff">− Списание</a>
    <a class="btn btn-outline" href="/admin/stock/export?<?= e($back) ?>">Скачать для Excel</a>
  </div>
</div>
<span class="muted">Видит только старший администратор. «Доход» и «Расход» — цифры из банкинга (эквайринг, оплата поставщикам, зарплата…). Продажи не вносим: пришло по накладным − списали = продали. Цена списания — из последней накладной, «продано на» — по розничной цене из «Цветов».</span>
<?php if ($msg && isset($msgs[$msg])): ?><p class="pill ok" style="align-self: flex-start"><?= e($msgs[$msg]) ?></p><?php endif ?>

<div class="stack" style="gap: 10px">
  <div class="tabs">
    <?php foreach (['week' => 'Эта неделя', 'month' => 'Этот месяц', 'prev' => 'Прошлый месяц', 'year' => 'Год'] as $k => $label): ?>
      <a class="chip chip-sm <?= $preset === $k ? 'on' : '' ?>" href="<?= e($q(['p' => $k, 'from' => null, 'to' => null])) ?>"><?= e($label) ?></a>
    <?php endforeach ?>
    <form class="stk-dates" method="get" action="/admin/stock">
      <input type="hidden" name="p" value="custom">
      <?php if ($shop): ?><input type="hidden" name="shop" value="<?= e($shop) ?>"><?php endif ?>
      <input class="input" type="date" name="from" value="<?= e($from) ?>" aria-label="С">
      <span class="muted">–</span>
      <input class="input" type="date" name="to" value="<?= e($to) ?>" aria-label="По">
      <button class="chip chip-sm <?= $preset === 'custom' ? 'on' : '' ?>">Показать</button>
    </form>
  </div>
  <div class="tabs">
    <a class="chip chip-sm <?= $shop === null ? 'on' : '' ?>" href="<?= e($q(['shop' => null])) ?>">Все магазины</a>
    <?php foreach ($shops as $s): ?>
      <a class="chip chip-sm <?= $shop === $s['code'] ? 'on' : '' ?>" href="<?= e($q(['shop' => $s['code']])) ?>"><?= e($s['name']) ?></a>
    <?php endforeach ?>
  </div>
  <b><?= e($periodText) ?><?= $shop ? ' · ' . e($shopName[$shop]) : '' ?></b>
</div>

<div class="stk-tiles">
  <div class="stk-tile"><span class="k">Закупили</span><span class="v"><?= $byn($t['buyKop']) ?></span><span class="small muted">цветы <?= $money($t['buyFlowersKop']) ?> · упаковка и др. <?= $money($t['buyOtherKop']) ?></span></div>
  <div class="stk-tile wo"><span class="k">Списали</span><span class="v"><?= $byn($t['woKop']) ?></span><span class="small muted"><?= $t['woPct'] === null ? '—' : $t['woPct'] . '% от закупки' ?></span></div>
  <div class="stk-tile"><span class="k">Продали цветов</span><span class="v"><?= e(Stock::qty($t['sold'])) ?> <small class="cur">шт.</small></span><span class="small muted">из <?= e(Stock::qty($t['in'])) ?> пришедших</span></div>
  <div class="stk-tile ok"><span class="k">Заработали на цветах</span><span class="v">≈ <?= $byn($t['earnedKop']) ?></span><span class="small muted">продано на <?= $money($t['soldKop']) ?> − закупка цветов</span></div>
  <div class="stk-tile"><span class="k">Заказы с сайта</span><span class="v"><?= $byn($t['siteKop']) ?></span><span class="small muted">кроме отменённых, по дате доставки</span></div>
</div>

<?php $md = $bank; $cat = fn (array $by, array $labels, string $color) => $by ? '<div class="stk-bars">' . implode('', array_map(fn ($k) => '<div class="stk-bar"><span>' . e($labels[$k] ?? $k) . '</span><div class="track"><div class="fill" style="width: ' . round($by[$k] / max(1, max($by)) * 100, 1) . '%; background: ' . $color . '"></div></div><span class="n">' . $money($by[$k]) . '</span></div>', array_keys($by))) . '</div>' : '<p class="small muted">Нет.</p>'; ?>
<div class="card stack" style="gap: 14px">
  <div class="row between"><h3>Деньги по банку</h3><?= $legend($bankSeries) ?></div>
  <div class="stk-tiles">
    <div class="stk-tile ok"><span class="k">Пришло</span><span class="v"><?= $byn($md['inKop']) ?></span></div>
    <div class="stk-tile wo"><span class="k">Ушло</span><span class="v"><?= $byn($md['outKop']) ?></span></div>
    <div class="stk-tile"><span class="k">Итог</span><span class="v" style="color: <?= $md['netKop'] < 0 ? 'var(--danger)' : 'var(--olive)' ?>"><?= $byn($md['netKop']) ?></span><span class="small muted">пришло − ушло</span></div>
  </div>
  <div class="stk-grid">
    <div class="stack" style="gap: 8px"><b>Откуда пришло</b><?= $cat($md['byIn'], Stock::MONEY_IN, 'var(--olive)') ?></div>
    <div class="stack" style="gap: 8px"><b>Куда ушло</b><?= $cat($md['byOut'], Stock::MONEY_OUT, 'var(--danger)') ?></div>
    <div class="stack" style="gap: 8px"><b>По месяцам, BYN</b><?= $chart($bankSeries) ?></div>
  </div>
  <?php if (!$entries): ?>
    <p class="small muted">За этот период ничего не внесено. Нажмите «+ Доход» или «− Расход» и впишите цифру из банкинга.</p>
  <?php else: ?>
    <details><summary class="small" style="cursor: pointer; font-weight: 700">Все записи (<?= count($entries) ?>)</summary>
    <div class="table-wrap"><table class="table stk-table">
      <thead><tr><th>Дата</th><th>Что</th><th>Описание</th><th>Магазин</th><th class="r">Сумма</th><th>Кто внёс</th><th></th></tr></thead>
      <tbody>
        <?php foreach ($entries as $m): $in = $m['direction'] === 'IN'; ?>
          <tr>
            <td><?= e(admin_date($m['entryDate'])) ?></td>
            <td><?= e(($in ? Stock::MONEY_IN : Stock::MONEY_OUT)[$m['category']] ?? $m['category']) ?></td>
            <td><?= e($m['description']) ?></td>
            <td><?= e($shopName[$m['shop']] ?? 'Общее') ?></td>
            <td class="r <?= $in ? 'stk-in' : 'stk-out' ?>"><b><?= $in ? '+' : '−' ?> <?= $money((int) $m['amountKop']) ?></b></td>
            <td class="small muted"><?= e($m['createdBy']) ?></td>
            <td><form method="post" action="/admin/stock/money/<?= (int) $m['id'] ?>/delete" onsubmit="return confirm('Удалить эту запись?')"><?= Auth::csrfField() ?><input type="hidden" name="back" value="<?= e($back) ?>"><button class="btn-ghost small" aria-label="Удалить">×</button></form></td>
          </tr>
        <?php endforeach ?>
      </tbody>
    </table></div>
    </details>
  <?php endif ?>
</div>

<div class="card stack" style="gap: 12px">
  <h3>Какой магазин сколько приносит</h3>
  <div class="table-wrap"><table class="table stk-table">
    <thead><tr><th>Магазин</th><th class="r">Закупили</th><th class="r">Списали</th><th class="r">% списания</th><th class="r">Продано на</th><th class="r">Из них сайт</th><th class="r">Заработали на цветах</th></tr></thead>
    <tbody>
      <?php $maxEarn = max(1, ...array_values(array_map(fn ($s) => $s["earnedKop"], $report["shops"]))); ?>
      <?php foreach ($shops as $s): $r = $report['shops'][$s['code']]; ?>
        <tr<?= $shop && $shop !== $s['code'] ? ' class="off"' : '' ?>>
          <td><b><?= e($s['name']) ?></b></td>
          <td class="r"><?= $money($r['buyKop']) ?></td>
          <td class="r stk-out"><?= $money($r['woKop']) ?></td>
          <td class="r <?= ($r['woPct'] ?? 0) >= 15 ? 'hi' : '' ?>"><?= $r['woPct'] === null ? '—' : $r['woPct'] . '%' ?></td>
          <td class="r stk-in"><?= $money($r['soldKop']) ?></td>
          <td class="r"><?= $money($r['siteKop'] ?? 0) ?><?= !empty($r['siteOrders']) ? ' <span class="small muted">(' . $r['siteOrders'] . ')</span>' : '' ?></td>
          <td class="r"><b><?= $money($r['earnedKop']) ?></b></td>
        </tr>
      <?php endforeach ?>
    </tbody>
  </table></div>
  <div class="stk-bars">
    <?php foreach ($shops as $s): $r = $report['shops'][$s['code']]; ?>
      <div class="stk-bar"><span><?= e($s['name']) ?></span><div class="track"><div class="fill" style="width: <?= max(0, round($r['earnedKop'] / $maxEarn * 100, 1)) ?>%; background: <?= $shopColor[$s['code']] ?>"></div></div><span class="n"><?= $money($r['earnedKop']) ?></span></div>
    <?php endforeach ?>
  </div>
</div>

<div class="stk-grid">
  <div class="card stack" style="gap: 10px">
    <div class="row between"><h3>Списание по месяцам, BYN</h3><?= $legend($byShop('wo')) ?></div>
    <?= $chart($byShop('wo')) ?>
  </div>
  <div class="card stack" style="gap: 10px">
    <div class="row between"><h3>Закупка по месяцам, BYN</h3><?= $legend($byShop('buy')) ?></div>
    <?= $chart($byShop('buy')) ?>
  </div>
  <div class="card stack" style="gap: 10px">
    <h3>Почему списываем</h3>
    <?php if (!$report['reasons']): ?><p class="small muted">За этот период списаний нет.</p><?php else: $maxR = max($report['reasons']); ?>
      <div class="stk-bars">
        <?php foreach ($report['reasons'] as $code => $kop): ?>
          <div class="stk-bar"><span><?= e(Stock::REASONS[$code]) ?></span><div class="track"><div class="fill" style="width: <?= round($kop / max(1, $maxR) * 100, 1) ?>%; background: var(--danger)"></div></div><span class="n"><?= $money($kop) ?></span></div>
        <?php endforeach ?>
      </div>
    <?php endif ?>
  </div>
</div>

<div class="card stack" style="gap: 12px">
  <h3>По позициям</h3>
  <?php if (!$report['items']): ?>
    <p class="muted">За этот период ничего не внесено. Начните с кнопки «+ Накладная»: сфотографируйте накладную или впишите строки вручную.</p>
  <?php else: ?>
    <div class="table-wrap"><table class="table stk-table">
      <thead><tr><th>Позиция</th><th class="r">Пришло</th><th class="r">Списали</th><th class="r">Продали</th><th class="r">% списания</th><th class="r">Закупка</th><th class="r">Списано на</th><th class="r">Продано на</th></tr></thead>
      <tbody>
        <?php foreach ($report['items'] as $i): ?>
          <tr>
            <td><?= e($i['name']) ?><?= $i['kind'] !== 'FLOWER' ? ' <span class="pill">' . e(Stock::KINDS[$i['kind']]) . '</span>' : '' ?><?= $i['noCost'] ? ' <span class="pill warn" title="Нет накладной с этой позицией — не знаем цену закупки">нет цены</span>' : '' ?></td>
            <td class="r"><?= e(Stock::qty($i['in'])) ?></td>
            <td class="r stk-out"><?= e(Stock::qty($i['wo'])) ?></td>
            <td class="r"><?= e(Stock::qty($i['sold'])) ?></td>
            <td class="r <?= ($i['woPct'] ?? 0) >= 15 ? 'hi' : '' ?>"><?= $i['woPct'] === null ? '—' : $i['woPct'] . '%' ?></td>
            <td class="r"><?= $money($i['inKop']) ?></td>
            <td class="r stk-out"><?= $money($i['woKop']) ?></td>
            <td class="r stk-in"><?= $i['kind'] === 'FLOWER' ? $money($i['soldKop']) : '—' ?></td>
          </tr>
        <?php endforeach ?>
        <tr class="total"><td>Итого</td><td></td><td></td><td></td><td class="r"><?= $t['woPct'] === null ? '—' : $t['woPct'] . '%' ?></td><td class="r"><?= $money($t['buyKop']) ?></td><td class="r stk-out"><?= $money($t['woKop']) ?></td><td class="r stk-in"><?= $money($t['soldKop']) ?></td></tr>
      </tbody>
    </table></div>
    <span class="small muted">Если на конец периода в вазах ещё стоят цветы, они попадут в «продали». Цветы закупаются каждую неделю, поэтому за месяц это почти не влияет.</span>
  <?php endif ?>
</div>

<div class="card stack" style="gap: 6px">
  <h3>Накладные за период</h3>
  <?php if (!$purchases): ?><p class="small muted">Нет.</p><?php endif ?>
  <?php foreach ($purchases as $p):
      $sum = array_sum(array_column($p['lines'], 'sumKop'));
      $per = [];
      foreach ($p['lines'] as $l) $per[$l['itemId']][$l['shop']] = (float) $l['qty']; ?>
    <details class="stk-doc">
      <summary><b><?= e(admin_date($p['docDate'])) ?></b> <span><?= e($p['supplier'] ?: 'Без поставщика') ?><?= $p['docNumber'] !== '' ? ' · № ' . e($p['docNumber']) : '' ?></span> <b><?= $byn($sum) ?></b>
        <?php if ($p['docTotalKop'] !== null && (int) $p['docTotalKop'] !== $sum): ?><span class="pill warn">в накладной <?= e(Money::format((int) $p['docTotalKop'])) ?></span><?php endif ?>
        <span class="small muted"><?= e($p['createdBy']) ?></span></summary>
      <div class="table-wrap"><table class="table stk-table">
        <thead><tr><th>Позиция</th><?php foreach ($shops as $s): ?><th class="r"><?= e($s['name']) ?></th><?php endforeach ?><th class="r">Сумма</th></tr></thead>
        <tbody>
          <?php foreach ($per as $itemId => $qs): ?>
            <tr><td><?= e($itemName($itemId)) ?></td><?php foreach ($shops as $s): ?><td class="r"><?= isset($qs[$s['code']]) ? e(Stock::qty($qs[$s['code']])) : '—' ?></td><?php endforeach ?>
              <td class="r"><?= $money(array_sum(array_map(fn ($l) => $l['itemId'] === $itemId ? (int) $l['sumKop'] : 0, $p['lines']))) ?></td></tr>
          <?php endforeach ?>
        </tbody>
      </table></div>
      <?php if ($p['comment'] !== ''): ?><p class="small muted"><?= e($p['comment']) ?></p><?php endif ?>
      <form method="post" action="/admin/stock/purchase/<?= (int) $p['id'] ?>/delete" onsubmit="return confirm('Удалить эту накладную?')">
        <?= Auth::csrfField() ?><input type="hidden" name="back" value="<?= e($back) ?>">
        <button class="btn-ghost small">Удалить накладную</button>
      </form>
    </details>
  <?php endforeach ?>
</div>

<div class="card stack" style="gap: 6px">
  <h3>Списания за период</h3>
  <?php if (!$writeoffs): ?><p class="small muted">Нет.</p><?php else: ?>
    <div class="table-wrap"><table class="table stk-table">
      <thead><tr><th>Дата</th><th>Магазин</th><th>Что</th><th class="r">Сколько</th><th>Причина</th><th class="r">Сумма</th><th>Кто внёс</th><th></th></tr></thead>
      <tbody>
        <?php foreach ($writeoffs as $w): $cost = Stock::unitCost($history, (int) $w['itemId'], $w['woDate']); ?>
          <tr>
            <td><?= e(admin_date($w['woDate'])) ?></td>
            <td><?= e($shopName[$w['shop']] ?? $w['shop']) ?></td>
            <td><?= e($itemName((int) $w['itemId'])) ?><?= $w['comment'] !== '' ? '<div class="small muted">' . e($w['comment']) . '</div>' : '' ?></td>
            <td class="r"><?= e(Stock::qty((float) $w['qty'])) ?></td>
            <td><?= e(Stock::REASONS[$w['reason']] ?? $w['reason']) ?></td>
            <td class="r stk-out"><?= $cost === null ? '<span class="pill warn">нет цены</span>' : $money((int) round($cost * (float) $w['qty'])) ?></td>
            <td class="small muted"><?= e($w['createdBy']) ?></td>
            <td><form method="post" action="/admin/stock/writeoff/<?= (int) $w['id'] ?>/delete" onsubmit="return confirm('Удалить эту строку списания?')"><?= Auth::csrfField() ?><input type="hidden" name="back" value="<?= e($back) ?>"><button class="btn-ghost small" aria-label="Удалить">×</button></form></td>
          </tr>
        <?php endforeach ?>
      </tbody>
    </table></div>
  <?php endif ?>
</div>

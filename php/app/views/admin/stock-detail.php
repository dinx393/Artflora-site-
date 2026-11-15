<?php
// «Финансы» → детализация одной цифры: из чего она сложилась. Маршрут — GET /admin/stock/detail (routes/admin-stock.php).
$shopName = array_column($shops, 'name', 'code');
$period = ['p' => $preset, 'from' => $preset === 'custom' ? $from : null, 'to' => $preset === 'custom' ? $to : null];
$keep = array_filter(['m' => $m, 'kind' => $f['kind'], 'reason' => $f['reason'], 'dir' => $f['dir'], 'cat' => $f['cat'], 'item' => $f['item'] ?: null]);
$q = fn (array $ch) => '/admin/stock/detail?' . http_build_query(array_filter(array_merge($keep, $period, ['shop' => $shop], $ch), fn ($v) => $v !== null && $v !== ''));
$link = fn (string $metric, array $more = []) => '/admin/stock/detail?' . http_build_query(array_filter(array_merge(['m' => $metric], $period, ['shop' => $shop], $more), fn ($v) => $v !== null && $v !== ''));
$back = '/admin/stock?' . http_build_query(array_filter($period + ['shop' => $shop]));
$money = fn (int $kop) => e(preg_replace('/ BYN$/', '', Money::format($kop)));
$periodText = $from === $to ? admin_date($from) . ' ' . substr($from, 0, 4) : admin_date($from) . ' – ' . admin_date($to) . ' ' . substr($to, 0, 4);
$itemName = fn (int $id) => $items[$id]['name'] ?? '?';
$itemLink = fn (int $id) => '<a href="' . e($link('item', ['item' => $id])) . '">' . e($itemName($id)) . '</a>';
$sub = [];
if ($f['kind'] === 'flowers') $sub[] = 'только цветы';
if ($f['kind'] === 'other') $sub[] = 'упаковка и прочее';
if ($f['reason'] !== '') $sub[] = 'причина: ' . (Stock::REASONS[$f['reason']] ?? $f['reason']);
if ($f['dir'] === 'IN') $sub[] = 'пришло';
if ($f['dir'] === 'OUT') $sub[] = 'ушло';
if ($f['dir'] === 'OWNER') $sub[] = 'директору';
if ($f['dir'] === 'BIZOUT') $sub[] = 'ушло на бизнес';
if ($f['dir'] === 'BIZ') $sub[] = 'бизнес, без директора';
if ($f['cat'] !== '') $sub[] = ($m === 'site' ? 'оплачено: ' : '') . ((Stock::MONEY_IN + Stock::MONEY_OUT)[$f['cat']] ?? $f['cat']);
?>
<style>
  .stk-table td.r, .stk-table th.r { text-align: right; font-variant-numeric: tabular-nums; white-space: nowrap; }
  .stk-table tr.total td { font-weight: 800; border-top: 2px solid var(--line); }
  .stk-table a { color: var(--cherry); font-weight: 700; }
  .stk-in { color: var(--olive); } .stk-out { color: var(--danger); }
  .stk-sum { font-size: 26px; font-weight: 800; color: var(--cherry); font-variant-numeric: tabular-nums; }
</style>

<a class="small" href="<?= e($back) ?>">← Финансы</a>
<div class="stack" style="gap: 4px">
  <h1><?= e($heading) ?></h1>
  <b><?= e($periodText) ?><?= $shop ? ' · ' . e($shopName[$shop]) : '' ?><?= $sub ? ' · ' . e(implode(' · ', $sub)) : '' ?></b>
</div>
<div class="tabs">
  <a class="chip chip-sm <?= $shop === null ? 'on' : '' ?>" href="<?= e($q(['shop' => null])) ?>">Все магазины</a>
  <?php foreach ($shops as $s): ?>
    <a class="chip chip-sm <?= $shop === $s['code'] ? 'on' : '' ?>" href="<?= e($q(['shop' => $s['code']])) ?>"><?= e($s['name']) ?></a>
  <?php endforeach ?>
</div>

<?php if (in_array($_GET['msg'] ?? '', ['updated', 'deleted'], true)): ?><p class="pill ok" style="align-self: flex-start"><?= $_GET['msg'] === 'updated' ? 'Исправлено.' : 'Удалено.' ?></p><?php endif ?>
<?php if (!empty($_GET['merged'])): ?><p class="pill ok" style="align-self: flex-start">Связано. Теперь у позиции есть розничная цена из «Цветов».</p><?php endif ?>
<?php if ($m === 'item' && ($cur = $items[$f['item']] ?? null) && $cur['flowerId'] === null): ?>
  <form id="link" class="card stack" style="gap: 10px; background: #fff6e5; border-color: #f1d9a8" method="post" action="/admin/stock/item/<?= (int) $cur['id'] ?>/merge">
    <?= Auth::csrfField() ?>
    <input type="hidden" name="back" value="<?= e(http_build_query(array_filter($period + ['shop' => $shop]))) ?>">
    <b><?= $cur['kind'] === 'FLOWER' ? 'Какой это цветок в справочнике «Цветы»?' : 'Это то же самое, что другая позиция?' ?></b>
    <span class="small">«<?= e($cur['name']) ?>» станет этим цветком: все накладные и списания перейдут к нему, «Продано на» посчитается по его розничной цене. Следующие накладные с таким названием сайт свяжет сам.</span>
    <div class="row" style="gap: 8px">
      <select class="input" name="to" required style="flex: 1; min-width: 220px">
        <option value="">— выберите —</option>
        <optgroup label="Цветы из справочника">
          <?php foreach ($items as $i): if ($i['flowerId'] === null) continue; ?><option value="<?= (int) $i['id'] ?>"><?= e($i['name']) ?><?= $i['retailKop'] !== null ? ' — ' . e(Money::format($i['retailKop'])) . '/шт.' : '' ?></option><?php endforeach ?>
        </optgroup>
        <optgroup label="Другие позиции (если это дубль)">
          <?php foreach ($items as $i): if ($i['flowerId'] !== null || $i['id'] === $cur['id']) continue; ?><option value="<?= (int) $i['id'] ?>"><?= e($i['name']) ?></option><?php endforeach ?>
        </optgroup>
      </select>
      <button class="btn">Связать</button>
    </div>
  </form>
<?php endif ?>

<?php if ($m === 'site'): $sum = array_sum(array_column($orders, 'totalKop')); ?>
  <div class="card stack" style="gap: 10px">
    <div class="row between"><span class="stk-sum"><?= admin_byn($sum) ?></span><span class="muted"><?= count($orders) ?> заказ(ов), кроме отменённых, по дате доставки</span></div>
    <?php if (!$orders): ?><p class="muted">Заказов за этот период нет.</p><?php else: ?>
      <div class="table-wrap"><table class="table stk-table">
        <thead><tr><th>№</th><th>Дата</th><th>Что заказали</th><th>Покупатель</th><th>Магазин</th><th>Статус</th><th>Оплата</th><th class="r">Сумма</th></tr></thead>
        <tbody>
          <?php foreach ($orders as $o): ?>
            <tr>
              <td><a href="/admin/orders/<?= (int) $o['id'] ?>">№ <?= (int) $o['number'] ?></a></td>
              <td><?= e(admin_date($o['deliveryDate'])) ?> <span class="small muted"><?= e($o['deliverySlot'] ?? '') ?></span></td>
              <td><?= e($o['what']) ?></td>
              <td><?= e($o['customerName']) ?></td>
              <td><?= e($shopName[$o['shop']] ?? $o['shop']) ?><div class="small muted"><?= $o['deliveryMethod'] === 'PICKUP' ? 'самовывоз' : 'доставка' ?></div></td>
              <td><?= e(ADMIN_STATUS_LABEL[$o['status']] ?? $o['status']) ?></td>
              <td><?= e(['CARD' => 'WebPay', 'ERIP' => 'ЕРИП', 'VIBER_LINK' => 'Viber', 'INVOICE' => 'Безнал', 'SHOP' => 'В магазине'][$o['paymentMethod']] ?? $o['paymentMethod']) ?><?= $o['paid'] ? ' <span class="pill ok">оплачен</span>' : '' ?></td>
              <td class="r"><b><?= $money((int) $o['totalKop']) ?></b></td>
            </tr>
          <?php endforeach ?>
          <tr class="total"><td colspan="7">Итого</td><td class="r"><?= $money($sum) ?></td></tr>
        </tbody>
      </table></div>
    <?php endif ?>
  </div>
<?php endif ?>

<?php if ($m === 'buy' || $m === 'item'): $sum = array_sum(array_column($lines, 'sumKop')); ?>
  <div class="card stack" style="gap: 10px">
    <div class="row between"><h3><?= $m === 'item' ? 'Пришло по накладным' : 'Строки накладных' ?></h3><span class="stk-sum"><?= admin_byn($sum) ?></span></div>
    <?php if ($m === 'buy'): ?>
      <div class="tabs">
        <?php foreach (['' => 'Всё', 'flowers' => 'Цветы', 'other' => 'Упаковка и прочее'] as $k => $label): ?>
          <a class="chip chip-sm <?= $f['kind'] === $k ? 'on' : '' ?>" href="<?= e($q(['kind' => $k ?: null])) ?>"><?= e($label) ?></a>
        <?php endforeach ?>
      </div>
    <?php endif ?>
    <?php if (!$lines): ?><p class="muted">Нет.</p><?php else: ?>
      <div class="table-wrap"><table class="table stk-table">
        <thead><tr><th>Дата</th><th>Накладная</th><th>Позиция</th><th>Магазин</th><th class="r">Сколько</th><th class="r">Цена за шт.</th><th class="r">Сумма</th></tr></thead>
        <tbody>
          <?php foreach ($lines as $l): $qty = (float) $l['qty']; ?>
            <tr>
              <td><?= e(admin_date($l['docDate'])) ?></td>
              <td><?= e($l['supplier'] ?: 'Без поставщика') ?><?= $l['docNumber'] !== '' ? ' <span class="small muted">№ ' . e($l['docNumber']) . '</span>' : '' ?></td>
              <td><?= $itemLink((int) $l['itemId']) ?></td>
              <td><?= e($shopName[$l['shop']] ?? $l['shop']) ?></td>
              <td class="r"><?= e(Stock::qty($qty)) ?></td>
              <td class="r"><?= $qty > 0 ? $money((int) round($l['sumKop'] / $qty)) : '—' ?></td>
              <td class="r"><b><?= $money((int) $l['sumKop']) ?></b></td>
            </tr>
          <?php endforeach ?>
          <tr class="total"><td colspan="4">Итого</td><td class="r"><?= e(Stock::qty(array_sum(array_map(fn ($l) => (float) $l['qty'], $lines)))) ?></td><td></td><td class="r"><?= $money($sum) ?></td></tr>
        </tbody>
      </table></div>
    <?php endif ?>
  </div>
<?php endif ?>

<?php if ($m === 'wo' || $m === 'item'): $sum = array_sum(array_map(fn ($w) => $w['kop'] ?? 0, $writeoffs)); ?>
  <div class="card stack" style="gap: 10px">
    <div class="row between"><h3>Списания</h3><span class="stk-sum" style="color: var(--danger)"><?= admin_byn($sum) ?></span></div>
    <?php if ($m === 'wo'): ?>
      <div class="tabs">
        <a class="chip chip-sm <?= $f['reason'] === '' ? 'on' : '' ?>" href="<?= e($q(['reason' => null])) ?>">Все причины</a>
        <?php foreach (Stock::REASONS as $k => $label): ?>
          <a class="chip chip-sm <?= $f['reason'] === $k ? 'on' : '' ?>" href="<?= e($q(['reason' => $k])) ?>"><?= e($label) ?></a>
        <?php endforeach ?>
      </div>
    <?php endif ?>
    <?php if (!$writeoffs): ?><p class="muted">Нет.</p><?php else: ?>
      <div class="table-wrap"><table class="table stk-table">
        <thead><tr><th>Дата</th><th>Магазин</th><th>Что</th><th class="r">Сколько</th><th>Причина</th><th class="r">Сумма</th><th>Кто внёс</th></tr></thead>
        <tbody>
          <?php foreach ($writeoffs as $w): ?>
            <tr>
              <td><?= e(admin_date($w['woDate'])) ?></td>
              <td><?= e($shopName[$w['shop']] ?? $w['shop']) ?></td>
              <td><?= $itemLink((int) $w['itemId']) ?><?= $w['comment'] !== '' ? '<div class="small muted">' . e($w['comment']) . '</div>' : '' ?></td>
              <td class="r"><?= e(Stock::qty((float) $w['qty'])) ?></td>
              <td><?= e(Stock::REASONS[$w['reason']] ?? $w['reason']) ?></td>
              <td class="r stk-out"><?= $w['kop'] === null ? '<span class="pill warn">нет цены</span>' : $money($w['kop']) ?></td>
              <td class="small muted"><?= e($w['createdBy']) ?></td>
            </tr>
          <?php endforeach ?>
          <tr class="total"><td colspan="3">Итого</td><td class="r"><?= e(Stock::qty(array_sum(array_map(fn ($w) => (float) $w['qty'], $writeoffs)))) ?></td><td></td><td class="r stk-out"><?= $money($sum) ?></td><td></td></tr>
        </tbody>
      </table></div>
    <?php endif ?>
  </div>
<?php endif ?>

<?php if ($m === 'sold'): $flowers = array_filter($report['items'], fn ($i) => $i['kind'] === 'FLOWER'); $t = $report['total']; ?>
  <div class="card stack" style="gap: 10px">
    <div class="row between"><h3>По каждому цветку</h3><span class="stk-sum" style="color: var(--olive)">≈ <?= admin_byn($t['earnedKop']) ?></span></div>
    <span class="small muted">Продали = пришло − списали. «Продано на» = продали × розничная цена из «Цветов» на день накладной (меняли цену — сайт помнит старую с 15.11.2026). «Заработали» = продано на − закупка этого цветка.</span>
    <?php if (!$flowers): ?><p class="muted">Нет.</p><?php else: ?>
      <div class="table-wrap"><table class="table stk-table">
        <thead><tr><th>Цветок</th><th class="r">Пришло</th><th class="r">Списали</th><th class="r">Продали</th><th class="r">Розница сейчас</th><th class="r">Продано на</th><th class="r">Закупка</th><th class="r">Заработали</th></tr></thead>
        <tbody>
          <?php foreach ($flowers as $i): $earn = $i['soldKop'] - $i['inKop']; ?>
            <tr>
              <td><?= $itemLink((int) $i['id']) ?></td>
              <td class="r"><?= e(Stock::qty($i['in'])) ?></td>
              <td class="r stk-out"><?= e(Stock::qty($i['wo'])) ?></td>
              <td class="r"><b><?= e(Stock::qty($i['sold'])) ?></b></td>
              <td class="r"><?= $i['retailKop'] === null ? '<span class="pill warn">нет цены</span>' : $money((int) $i['retailKop']) ?></td>
              <td class="r stk-in"><?= $money($i['soldKop']) ?></td>
              <td class="r"><?= $money($i['inKop']) ?></td>
              <td class="r <?= $earn < 0 ? 'stk-out' : '' ?>"><b><?= $money($earn) ?></b></td>
            </tr>
          <?php endforeach ?>
          <tr class="total"><td>Итого</td><td class="r"><?= e(Stock::qty($t['in'])) ?></td><td class="r stk-out"><?= e(Stock::qty($t['wo'])) ?></td><td class="r"><?= e(Stock::qty($t['sold'])) ?></td><td></td><td class="r stk-in"><?= $money($t['soldKop']) ?></td><td class="r"><?= $money($t['buyFlowersKop']) ?></td><td class="r"><?= $money($t['earnedKop']) ?></td></tr>
        </tbody>
      </table></div>
    <?php endif ?>
  </div>
<?php endif ?>

<?php if ($m === 'bank'):
    $in = array_sum(array_map(fn ($e) => $e['direction'] === 'IN' ? (int) $e['amountKop'] : 0, $entries));
    $out = array_sum(array_map(fn ($e) => $e['direction'] === 'OUT' ? (int) $e['amountKop'] : 0, $entries)); ?>
  <div class="card stack" style="gap: 10px">
    <div class="row between"><h3>Записи</h3><span class="stk-sum"><?= in_array($f['dir'], ['OUT', 'OWNER', 'BIZOUT'], true) ? '− ' . admin_byn($out) : ($f['dir'] === 'IN' ? '+ ' . admin_byn($in) : admin_byn($in - $out)) ?></span></div>
    <div class="tabs">
      <?php foreach (['' => 'Всё', 'IN' => 'Пришло', 'OUT' => 'Ушло всё', 'BIZOUT' => 'Ушло на бизнес', 'BIZ' => 'Бизнес без директора', 'OWNER' => 'Директору'] as $k => $label): ?>
        <a class="chip chip-sm <?= $f['dir'] === $k && $f['cat'] === '' ? 'on' : '' ?>" href="<?= e($q(['dir' => $k ?: null, 'cat' => null])) ?>"><?= e($label) ?></a>
      <?php endforeach ?>
    </div>
    <?php if (!$entries): ?><p class="muted">Нет.</p><?php else: ?>
      <div class="table-wrap"><table class="table stk-table">
        <thead><tr><th>Дата</th><th>Что</th><th>Описание</th><th>Магазин</th><th class="r">Сумма</th><th>Кто внёс</th><th></th></tr></thead>
        <tbody>
          <?php foreach ($entries as $e): $isIn = $e['direction'] === 'IN'; ?>
            <tr>
              <td><?= e(admin_date($e['entryDate'])) ?></td>
              <td><a href="<?= e($q(['dir' => $e['direction'], 'cat' => $e['category']])) ?>"><?= e(($isIn ? Stock::MONEY_IN : Stock::MONEY_OUT)[$e['category']] ?? $e['category']) ?></a></td>
              <td><?= e($e['description']) ?></td>
              <td><?= e($shopName[$e['shop']] ?? 'Общее') ?></td>
              <td class="r <?= $isIn ? 'stk-in' : 'stk-out' ?>"><b><?= $isIn ? '+' : '−' ?> <?= $money((int) $e['amountKop']) ?></b></td>
              <td class="small muted"><?= e($e['createdBy']) ?></td>
              <td style="white-space: nowrap"><a class="btn-ghost small" href="/admin/stock/money?<?= e(http_build_query(['id' => $e['id'], 'back' => $q([])])) ?>">✎ Исправить</a> <form method="post" action="/admin/stock/money/<?= (int) $e['id'] ?>/delete" onsubmit="return confirm('Удалить эту запись?')" style="display: inline"><?= Auth::csrfField() ?><input type="hidden" name="back" value="<?= e($q([])) ?>"><button class="btn-ghost small" title="Удалить">× Удалить</button></form></td>
            </tr>
          <?php endforeach ?>
        </tbody>
      </table></div>
    <?php endif ?>
  </div>
<?php endif ?>

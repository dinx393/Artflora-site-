<?php
// «Финансы» → доходы и расходы из банка одним блоком из чата. Маршрут — routes/admin-stock.php (/admin/stock/money/import).
$shopName = array_column($shops, 'name', 'code');
$good = array_filter($rows, fn ($r) => !$r['error'] && !$r['dup']);
$sum = fn (string $dir) => array_sum(array_map(fn ($r) => $r['dir'] === $dir ? $r['sumKop'] : 0, $good));
?>
<div class="row between">
  <h1>Банк из чата</h1>
  <a class="btn-ghost" href="/admin/stock">← Финансы</a>
</div>
<span class="muted">Пришлите в чат скриншоты из банкинга — Claude соберёт блок. Скопируйте его целиком сюда, нажмите «Проверить», посмотрите таблицу и нажмите «Сохранить». То, что уже внесено (та же дата, категория и сумма), сайт пропустит сам.</span>
<?php if ($error): ?><p class="error"><?= e($error) ?></p><?php endif ?>
<form method="post" action="/admin/stock/money/import" class="card stack" style="gap: 12px">
  <?= Auth::csrfField() ?>
  <textarea class="input" name="text" rows="<?= $rows ? 4 : 12 ?>" placeholder='{"entries": [{"date": "2026-11-13", "dir": "IN", "cat": "acquiring", "sumKop": 148620, "shop": "uruchye", "desc": "Эквайринг Беларусбанк, терминал Уручье, за 12.11"}]}' style="font-family: monospace; font-size: 13px" required><?= e($text) ?></textarea>
  <div class="row" style="gap: 8px">
    <button class="btn <?= $rows ? 'btn-outline' : '' ?>" name="action" value="check">Проверить</button>
    <?php if ($good): ?><button class="btn btn-lg" name="action" value="save">Сохранить <?= count($good) ?> зап.</button><?php endif ?>
  </div>
</form>
<?php if ($rows): ?>
  <div class="card stack" style="gap: 10px">
    <div class="row between"><h3>Что будет внесено</h3><span class="small"><b style="color: var(--olive)">+ <?= e(Money::format($sum('IN'))) ?></b> · <b style="color: var(--danger)">− <?= e(Money::format($sum('OUT'))) ?></b></span></div>
    <div class="table-wrap"><table class="table">
      <thead><tr><th>Дата</th><th>Что</th><th>Описание</th><th>Магазин</th><th class="r">Сумма</th><th></th></tr></thead>
      <tbody>
        <?php foreach ($rows as $r): $in = $r['dir'] === 'IN'; $cats = $in ? Stock::MONEY_IN : Stock::MONEY_OUT; ?>
          <tr style="<?= $r['error'] || $r['dup'] ? 'opacity: .55' : '' ?>">
            <td><?= $r['error'] === 'дата' ? e($r['date']) : e(admin_date($r['date'])) ?></td>
            <td><?= e($cats[$r['cat']] ?? $r['catRaw']) ?><?= in_array($r['cat'], Stock::OWNER, true) ? ' <span class="small" style="color: #6b5a9e">· директору</span>' : '' ?></td>
            <td><?= e($r['desc']) ?></td>
            <td><?= e($shopName[$r['shop']] ?? 'Общее') ?></td>
            <td class="r" style="white-space: nowrap; color: <?= $in ? 'var(--olive)' : 'var(--danger)' ?>"><b><?= $in ? '+' : '−' ?> <?= $r['sumKop'] ? e(preg_replace('/ BYN$/', '', Money::format($r['sumKop']))) : '?' ?></b></td>
            <td class="small"><?= $r['error'] ? '<span class="pill warn">ошибка: ' . e($r['error']) . '</span>' : ($r['dup'] ? '<span class="pill">уже внесено — пропущу</span>' : '<span class="pill ok">ок</span>') ?></td>
          </tr>
        <?php endforeach ?>
      </tbody>
    </table></div>
  </div>
<?php endif ?>

<?php
// «Финансы» → внести доход или расход из банкинга. Маршрут — routes/admin-stock.php (/admin/stock/money).
$in = $dir === 'IN';
$cats = $in ? Stock::MONEY_IN : Stock::MONEY_OUT;
?>
<div class="row between">
  <h1><?= $entry ? 'Исправить: ' . ($in ? 'доход' : 'расход') : ($in ? '+ Доход' : '− Расход') ?></h1>
  <a class="btn-ghost" href="<?= e($entry ? $back : '/admin/stock') ?>">← Финансы</a>
</div>
<span class="muted"><?= $in ? 'Что пришло на счёт: эквайринг, ЕРИП, безнал от организаций, сданная наличка.' : 'Что ушло со счёта: поставщикам, зарплата, аренда, налоги, реклама.' ?> Цифры — как в банкинге.</span>
<?php if ($saved): ?><p class="pill ok" style="align-self: flex-start">Сохранено. Можно внести следующую строку.</p><?php endif ?>
<?php if ($error): ?><p class="error"><?= e($error) ?></p><?php endif ?>
<?php if (!$entry): ?><div class="tabs">
  <a class="chip chip-sm <?= $in ? 'on' : '' ?>" href="/admin/stock/money?dir=in&amp;date=<?= e($date) ?>">+ Доход</a>
  <a class="chip chip-sm <?= $in ? '' : 'on' ?>" href="/admin/stock/money?dir=out&amp;date=<?= e($date) ?>">− Расход</a>
</div><?php endif ?>
<form method="post" action="/admin/stock/money/save" class="card stack" style="gap: 14px; max-width: 560px">
  <?= Auth::csrfField() ?>
  <input type="hidden" name="dir" value="<?= $dir ?>">
  <?php if ($entry): ?><input type="hidden" name="id" value="<?= (int) $entry['id'] ?>"><input type="hidden" name="back" value="<?= e($back) ?>"><?php endif ?>
  <?php $v = $entry ?? []; ?>
  <label class="stack" style="gap: 4px"><span class="small muted">Дата</span><input class="input" type="date" name="date" value="<?= e($v['entryDate'] ?? $date) ?>" required></label>
  <label class="stack" style="gap: 4px"><span class="small muted"><?= $in ? 'Откуда пришло' : 'На что ушло' ?></span>
    <select class="input" name="category" required>
      <option value="">— выберите —</option>
      <?php foreach ($cats as $k => $label): ?><option value="<?= $k ?>"<?= ($v['category'] ?? '') === $k ? ' selected' : '' ?>><?= e($label) ?></option><?php endforeach ?>
    </select>
  </label>
  <label class="stack" style="gap: 4px"><span class="small muted">Сумма, BYN</span><input class="input" name="amount" inputmode="decimal" placeholder="например, 1 250,00" required autocomplete="off" value="<?= isset($v['amountKop']) ? e(preg_replace('/ BYN$/', '', Money::format((int) $v['amountKop']))) : '' ?>"></label>
  <label class="stack" style="gap: 4px"><span class="small muted">Магазин</span>
    <select class="input" name="shop">
      <option value="">Общее (не к одному магазину)</option>
      <?php foreach ($shops as $s): ?><option value="<?= e($s['code']) ?>"<?= ($v['shop'] ?? '') === $s['code'] ? ' selected' : '' ?>><?= e($s['name']) ?></option><?php endforeach ?>
    </select>
  </label>
  <label class="stack" style="gap: 4px"><span class="small muted">Описание: <?= $in ? 'от кого / за что пришло' : 'кому / за что заплатили' ?></span>
    <input class="input" name="description" maxlength="300" value="<?= e($v['description'] ?? '') ?>" placeholder="<?= $in ? 'Например: эквайринг Беларусбанк за 14.11, терминал Уручье' : 'Например: АП-Салют групп, оплата по ТТН 5414968' ?>">
  </label>
  <button class="btn btn-lg" style="align-self: flex-start"><?= $entry ? 'Сохранить исправление' : 'Сохранить' ?></button>
</form>

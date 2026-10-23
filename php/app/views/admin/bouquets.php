<?php
// Список букетов = src/app/admin/(panel)/bouquets/page.tsx
$tabs = [
    'all' => "Все · {$counts['all']}",
    'on' => "На сайте · {$counts['on']}",
    'off' => "Выключены · {$counts['off']}",
    'missing' => "Нет цветов · {$counts['missing']}",
];
?>
<div class="row between">
  <div class="stack" style="gap: 4px">
    <h1>Букеты</h1>
    <span class="muted"><?= $counts['on'] ?> на сайте, <?= $counts['off'] ?> выключено вручную, <?= $counts['missing'] ?> скрыто: нет цветов</span>
  </div>
  <form method="post" action="/admin/bouquets/new">
    <?= Auth::csrfField() ?>
    <button class="btn">+ Добавить букет</button>
  </form>
</div>
<div class="tabs">
  <?php foreach ($tabs as $k => $label): ?>
    <a href="/admin/bouquets?tab=<?= $k ?>" class="chip <?= $tab === $k ? 'on' : '' ?>"><?= e($label) ?></a>
  <?php endforeach ?>
</div>
<div class="table-wrap">
  <table class="table">
    <thead>
      <tr><th>Букет</th><th>Состав (M)</th><th>Упаковка</th><th>На сайте</th><th>Статус</th><th>Показывать</th></tr>
    </thead>
    <tbody>
      <?php foreach ($shown as ['b' => $b, 'vis' => $vis, 'main' => $main, 'price' => $price]): ?>
        <tr class="<?= $vis['visible'] ? '' : 'off' ?>">
          <td>
            <div class="row" style="gap: 12px; flex-wrap: nowrap">
              <img src="<?= e($b['photos'][0] ?? Catalog::PLACEHOLDER) ?>" alt="" style="width: 52px; height: 52px; border-radius: 10px; object-fit: cover">
              <div class="stack" style="gap: 2px">
                <a href="/admin/bouquets/<?= $b['id'] ?>" style="font-weight: 800"><?= e($b['name']) ?></a>
                <span class="small muted"><?= $b['collectionName'] !== null ? e("Коллекция «{$b['collectionName']}» · ") : '' ?><?= e(Occasions::FORMATS[$b['format']]['label']) ?></span>
              </div>
            </div>
          </td>
          <td class="small muted" style="max-width: 320px"><?= $main ? e(Pricing::compositionText($main['items'])) : '—' ?></td>
          <td><?= $main ? e(Money::format($main['packagingKop'])) : '—' ?></td>
          <td style="font-weight: 800"><?= e(Money::format($price)) ?></td>
          <td>
            <?php if ($vis['visible']): ?>
              <span class="pill ok">На сайте</span>
            <?php elseif ($vis['reason'] === 'disabled'): ?>
              <span class="pill">Выключен</span>
            <?php else: ?>
              <span class="pill warn">Скрыт: нет в наличии <?= e(mb_strtolower(implode(', ', $vis['missing']))) ?></span>
            <?php endif ?>
          </td>
          <td>
            <button type="button" class="switch <?= $b['enabled'] ? 'on' : '' ?>" data-toggle="<?= $b['id'] ?>"
              aria-label="<?= $b['enabled'] ? 'Выключить' : 'Включить' ?>" aria-pressed="<?= $b['enabled'] ? 'true' : 'false' ?>"></button>
          </td>
        </tr>
      <?php endforeach ?>
    </tbody>
  </table>
</div>
<p class="small muted">
  Цена букета = цветы по ценам из раздела «Цветы» + упаковка. Букет без нужного цветка скрыт автоматически и вернётся сам, когда цветок снова появится.
</p>

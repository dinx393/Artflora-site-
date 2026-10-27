<?php
// Список букетов = src/app/admin/(panel)/bouquets/page.tsx
$tabs = [
    'all' => "Все · {$counts['all']}",
    'on' => "На сайте · {$counts['on']}",
    'off' => "Выключены · {$counts['off']}",
    'missing' => "Нет цветов · {$counts['missing']}",
];
// Что ищет поиск по списку: название, адрес, коллекция, вид и цветы всех размеров
$searchText = function (array $b): string {
    $parts = [$b['name'], $b['slug'], $b['collectionName'] ?? '', Occasions::FORMATS[$b['format']]['label'] ?? ''];
    foreach ($b['sizes'] as $s) foreach ($s['items'] as $i) $parts[] = $i['flower']['name'];
    return str_replace('ё', 'е', mb_strtolower(implode(' ', $parts)));
};
$q = is_string($_GET['q'] ?? null) ? trim($_GET['q']) : '';
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
    <a href="/admin/bouquets?tab=<?= $k ?><?= $q !== '' ? '&amp;q=' . e(rawurlencode($q)) : '' ?>" data-tab="<?= $k ?>" class="chip <?= $tab === $k ? 'on' : '' ?>"><?= e($label) ?></a>
  <?php endforeach ?>
</div>
<div class="row" style="gap: 12px">
  <input type="search" class="input" data-bouquet-search value="<?= e($q) ?>" placeholder="Найти букет: название, коллекция или цветок" aria-label="Поиск букетов" autocomplete="off" style="flex: 1; max-width: 520px">
  <span class="small muted" data-search-count hidden></span>
</div>
<p class="small muted" data-search-empty hidden style="margin: 0">Ничего не нашлось. Проверьте написание или откройте вкладку «Все».</p>
<p class="small muted" style="margin: 0" data-order-hint>Порядок на сайте: потяните букет за ⠿ (на телефоне — пальцем) или нажмите стрелки, сохраняется сразу. Новый букет появляется первым.</p>
<p class="small" data-order-msg hidden></p>
<div class="table-wrap">
  <table class="table" data-bouquet-list>
    <thead>
      <tr><th aria-label="Порядок"></th><th>Букет</th><th>Состав (M)</th><th>Упаковка</th><th>На сайте</th><th>Статус</th><th>Показывать</th></tr>
    </thead>
    <tbody>
      <?php foreach ($shown as ['b' => $b, 'vis' => $vis, 'main' => $main, 'price' => $price]): ?>
        <tr class="<?= $vis['visible'] ? '' : 'off' ?>" data-id="<?= $b['id'] ?>" data-search="<?= e($searchText($b)) ?>">
          <td class="drag-cell">
            <span class="drag-handle" title="Перетащите, чтобы поменять порядок на сайте">⠿</span>
            <span class="drag-steps"><button type="button" aria-label="Выше" data-step="-1">▲</button><button type="button" aria-label="Ниже" data-step="1">▼</button></span>
          </td>
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

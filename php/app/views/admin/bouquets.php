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
// Фильтры (коллекция, цветок, вид, цена) работают в браузере, admin-bouquets.js: варианты — только те, что есть у букетов
$flowerIds = fn (array $b) => array_values(array_unique(array_merge([], ...array_map(fn ($s) => array_map(fn ($i) => $i['flower']['id'], $s['items']), $b['sizes']))));
$filterCollections = $filterFlowers = $filterFormats = [];
foreach ($all as ['b' => $b]) {
    if ($b['collectionId'] !== null) $filterCollections[$b['collectionId']] = $b['collectionName'];
    $filterFormats[$b['format']] = Occasions::FORMATS[$b['format']]['label'] ?? $b['format'];
    foreach ($b['sizes'] as $s) foreach ($s['items'] as $i) $filterFlowers[$i['flower']['id']] = $i['flower']['name'];
}
$sortNames = fn (array $a) => (function () use ($a) { uasort($a, fn ($x, $y) => strcmp(mb_strtolower($x), mb_strtolower($y))); return $a; })();
$filterCollections = $sortNames($filterCollections);
$filterFlowers = $sortNames($filterFlowers);
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
    <a href="/admin/bouquets?tab=<?= $k ?>" data-tab="<?= $k ?>" class="chip <?= $tab === $k ? 'on' : '' ?>"><?= e($label) ?></a>
  <?php endforeach ?>
</div>
<div class="row" style="gap: 12px">
  <input type="search" class="input" data-bouquet-search value="<?= e($q) ?>" placeholder="Найти букет: название, коллекция или цветок" aria-label="Поиск букетов" autocomplete="off" style="flex: 1; max-width: 520px">
  <span class="small muted" data-search-count hidden></span>
</div>
<div class="row admin-filters" style="gap: 10px; align-items: flex-end" data-bouquet-filters>
  <label class="field"><span>Коллекция</span>
    <select class="input" data-filter="collection">
      <option value="">Все</option>
      <option value="none">Без коллекции</option>
      <?php foreach ($filterCollections as $id => $name): ?><option value="<?= e($id) ?>"><?= e($name) ?></option><?php endforeach ?>
    </select>
  </label>
  <label class="field"><span>Цветок</span>
    <select class="input" data-filter="flower">
      <option value="">Любой</option>
      <?php foreach ($filterFlowers as $id => $name): ?><option value="<?= e($id) ?>"><?= e($name) ?></option><?php endforeach ?>
    </select>
  </label>
  <label class="field"><span>Вид</span>
    <select class="input" data-filter="format">
      <option value="">Любой</option>
      <?php foreach ($filterFormats as $code => $label): ?><option value="<?= e($code) ?>"><?= e($label) ?></option><?php endforeach ?>
    </select>
  </label>
  <label class="field"><span>Цена от, BYN</span><input class="input" type="number" min="0" step="5" inputmode="numeric" data-filter="from" style="width: 110px"></label>
  <label class="field"><span>до, BYN</span><input class="input" type="number" min="0" step="5" inputmode="numeric" data-filter="to" style="width: 110px"></label>
  <button type="button" class="btn-ghost small" data-filters-reset hidden>× Сбросить фильтры</button>
</div>
<p class="small muted" data-search-empty hidden style="margin: 0">Ничего не нашлось. Уберите один из фильтров или откройте вкладку «Все».</p>
<p class="small muted" style="margin: 0" data-order-hint>Порядок на сайте: потяните букет за ⠿ (на телефоне — пальцем) или нажмите стрелки, сохраняется сразу. Новый букет появляется первым.</p>
<p class="small" data-order-msg hidden></p>
<div class="table-wrap">
  <table class="table" data-bouquet-list>
    <thead>
      <tr><th aria-label="Порядок"></th><th>Букет</th><th>Состав (M)</th><th>Упаковка</th><th>На сайте</th><th>Статус</th><th>Показывать</th></tr>
    </thead>
    <tbody>
      <?php foreach ($shown as ['b' => $b, 'vis' => $vis, 'main' => $main, 'price' => $price]): ?>
        <tr class="<?= $vis['visible'] ? '' : 'off' ?>" data-id="<?= $b['id'] ?>" data-search="<?= e($searchText($b)) ?>"
          data-collection="<?= e($b['collectionId'] ?? 'none') ?>" data-format="<?= e($b['format']) ?>" data-flowers="|<?= e(implode('|', $flowerIds($b))) ?>|" data-price="<?= (int) $price ?>">
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

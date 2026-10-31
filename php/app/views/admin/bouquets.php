<?php
// Список букетов = src/app/admin/(panel)/bouquets/page.tsx
$tabs = [
    'all' => "Все · {$counts['all']}",
    'on' => "На сайте · {$counts['on']}",
    'off' => "Выключены · {$counts['off']}",
    'missing' => "Нет цветов · {$counts['missing']}",
    'swap' => "🔁 Будет замена · {$counts['swap']}",
    'timer' => "⏱ Собран сегодня · {$counts['timer']}",
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
    <span class="muted"><?= $counts['on'] ?> на сайте<?= $counts['swap'] ? " (из них {$counts['swap']} с заменой)" : '' ?>, <?= $counts['off'] ?> выключено вручную, <?= $counts['missing'] ?> скрыто: нет цветов</span>
  </div>
  <div class="row" style="gap: 8px">
    <form method="post" action="/admin/bouquets/new">
      <?= Auth::csrfField() ?>
      <input type="hidden" name="today" value="1">
      <button class="btn btn-outline" title="Готовый букет: сразу «Букет дня» на главной, сам скроется с сайта через <?= Catalog::TIMER_HOURS ?> ч">+ Собран сегодня</button>
    </form>
    <form method="post" action="/admin/bouquets/new">
      <?= Auth::csrfField() ?>
      <button class="btn">+ Добавить букет</button>
    </form>
  </div>
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
<div class="card stack" style="gap: 8px; padding: 14px 16px">
  <span class="small" data-order-hint><b>Порядок на сайте</b> идёт сам: <b>Букет дня</b> → <b>⭐ избранные</b> → <b>хиты и эксклюзивы недели</b> → остальные.
    Значки ставятся в карточке букета. Внутри своей группы букет можно передвинуть: потяните за ⠿ (на телефоне — пальцем) или нажмите стрелки, сохраняется сразу.
    Новый букет встаёт в конец списка и наверх не лезет.</span>
  <div class="row" style="gap: 14px; align-items: center">
    <form method="post" action="/admin/bouquets/shuffle" style="margin: 0">
      <?= Auth::csrfField() ?>
      <input type="hidden" name="tab" value="<?= e($tab) ?>">
      <button class="btn btn-outline" title="Букеты без значков встанут в случайном порядке. Букет дня, избранные, хиты и эксклюзивы останутся на своих местах">🔀 Перемешать остальные</button>
    </form>
    <label class="check"><input type="checkbox" data-shuffle-daily<?= $catalogCfg['shuffleDaily'] ? ' checked' : '' ?>> Перемешивать их сами каждый день</label>
  </div>
</div>
<p class="small" data-order-msg hidden></p>
<div class="table-wrap">
  <table class="table" data-bouquet-list>
    <thead>
      <tr><th aria-label="Порядок"></th><th>Букет</th><th>Состав (M)</th><th>Упаковка</th><th>На сайте</th><th>Статус</th><th>Показывать</th></tr>
    </thead>
    <tbody>
      <?php $lastRank = null; foreach ($shown as ['b' => $b, 'vis' => $vis, 'main' => $main, 'price' => $price, 'swaps' => $swaps]):
        $rank = Catalog::rank($b, $daySlug); ?>
        <?php if ($rank !== $lastRank): $lastRank = $rank; ?>
          <tr class="rank-head" data-rank-head="<?= $rank ?>"><td colspan="7" class="small muted" style="font-weight: 800; padding-top: 14px"><?= e(Catalog::RANK_NAMES[$rank]) ?></td></tr>
        <?php endif ?>
        <tr class="<?= $vis['visible'] ? '' : 'off' ?>" data-id="<?= $b['id'] ?>" data-rank="<?= $rank ?>" data-search="<?= e($searchText($b)) ?>"
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
                <span class="small muted"><?= $b['collectionName'] !== null ? e("Коллекция «{$b['collectionName']}» · ") : '' ?><?= e(Occasions::FORMATS[$b['format']]['label']) ?><?= $b['weddingOnly'] && in_array('wedding', $b['occasions'], true) ? ' · только в «Свадебных»' : '' ?></span>
                <?php $marks = array_filter([$rank === 0 ? 'Букет дня' : null, $b['isPinned'] ? '⭐ Избранный' : null, $b['isHit'] ? 'Хит недели' : null, $b['isExclusive'] ? 'Эксклюзив недели' : null]);
                  if ($marks || !empty($sold[$b['id']])): ?>
                  <span class="small"><?= e(implode(' · ', $marks)) ?><?= $marks && !empty($sold[$b['id']]) ? ' · ' : '' ?><?php if (!empty($sold[$b['id']])): ?><span class="muted" title="Сколько штук заказали за последние 2 месяца (отменённые заказы не считаются)">🛒 <?= (int) $sold[$b['id']] ?> за 2 мес.</span><?php endif ?></span>
                <?php endif ?>
              </div>
            </div>
          </td>
          <td class="small muted" style="max-width: 320px"><?= $main ? e(Pricing::compositionText($main['items'])) : '—' ?></td>
          <td><?= $main ? e(Money::format($main['packagingKop'])) : '—' ?></td>
          <td style="font-weight: 800"><?= e(Money::format($price)) ?></td>
          <td>
            <?php if ($vis['visible']): ?>
              <span class="pill ok">На сайте</span>
              <?php foreach ($swaps as $x): ?>
                <span class="pill swap" title="Этого цветка нет, в букете указана замена. Флорист увидит её в заказе">🔁 Будет замена: <?= e(mb_strtolower($x['flower'])) ?> → <?= e(mb_strtolower($x['to'])) ?></span>
              <?php endforeach ?>
            <?php elseif ($vis['reason'] === 'disabled' && $b['hiddenByTimerAt'] !== null): ?>
              <span class="pill" title="Таймер «собран сегодня» сработал <?= e(Catalog::timerDate($b['hiddenByTimerAt'])) ?>. Вернуть на сайт — переключателем справа">⏱ Скрыт по таймеру <?= e(Catalog::timerDate($b['hiddenByTimerAt'])) ?></span>
            <?php elseif ($vis['reason'] === 'disabled'): ?>
              <span class="pill">Выключен</span>
            <?php else: ?>
              <span class="pill warn">Скрыт: нет в наличии <?= e(mb_strtolower(implode(', ', $vis['missing']))) ?></span>
            <?php endif ?>
            <?php if ($b['hideAt'] !== null): ?>
              <span class="pill timer" title="Таймер «собран сегодня»: <?= e(Catalog::timerDate($b['hideAt'])) ?> букет сам выключится. Поменять или снять — в карточке букета">⏱ Скроется через <?= e(Catalog::timerLeft($b['hideAt'])) ?></span>
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
  Если в карточке букета для этого цветка указана замена и она есть в наличии, букет остаётся на сайте с пометкой 🔁 «Будет замена».
  ⏱ — таймер «собран сегодня»: в срок букет сам выключается (не удаляется), обратно его включают вручную.
</p>

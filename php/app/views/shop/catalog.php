<?php
// Каталог с фильтрами (src/app/(shop)/catalog/page.tsx, CatalogFilters, ScrollRow, SortSelect).
// Фильтры — обычные ссылки: адрес страницы хранит выбор. На телефоне панель «Фильтры» после выбора
// снова открывается, а прокрутка не прыгает наверх (shop.js, data-keep-scroll).
$pageSize = 24; // сколько букетов показывать сразу и добавлять по кнопке «Показать ещё»

// При смене фильтра список снова начинается с первых $pageSize букетов.
$href = function (array $sp, array $patch): string {
    $next = array_merge(array_diff_key($sp, ['n' => 1]), $patch);
    // Один фильтр, у которого есть свой раздел, — понятный адрес раздела (/catalog/rozy), иначе /catalog?…
    return Seo::catalogUrl($next);
};
$isOn = fn (array $sp, array $patch): bool => $patch && !array_filter(array_keys($patch), fn ($k) => ($sp[$k] ?? null) !== $patch[$k]);
// Быстрые разделы над каталогом
$sections = [
    ['Розы', ['flower' => 'Роза']],
    ['Экзотика', ['occasion' => 'exotic']],
    ['Шляпные коробки', ['format' => 'HATBOX']],
    ['Сумки с цветами', ['format' => 'BAG']],
    ['Композиции', ['format' => 'BASKET']],
    ['Поштучно', [], '/single'],
];
$val = fn (string $k) => $sp[$k] ?? null;
$shown = max($pageSize, (int) floor((float) ($sp['n'] ?? 0)));
$minDelivery = Money::format(Delivery::minKop($delivery));
$colorLabels = array_map(fn ($c) => mb_strtolower($c['label']), array_values(array_filter($colorList, fn ($c) => in_array($c['slug'], $colors, true))));
$activeFilters = array_values(array_filter([$range['label'] ?? null, $val('flower'), $colorLabels ? 'цвет: ' . implode(', ', $colorLabels) : null]));
$allBouquets = !$val('occasion') && !$val('flower') && !$val('format') && !$val('collection') && !$val('q');
$format = Occasions::FORMATS[$val('format') ?? ''] ?? null;
$activeCount = count(array_filter([$range, $occ, $val('flower'), $format])) + count($colors);
$sort = in_array($val('sort'), ['cheap', 'expensive'], true) ? $val('sort') : '';
$sortHrefs = ['' => $href($sp, ['sort' => null]), 'cheap' => $href($sp, ['sort' => 'cheap']), 'expensive' => $href($sp, ['sort' => 'expensive'])];
$f = fn (array $patch) => e($href($sp, $patch));
?>
<section class="wrap catalog-head">
  <nav class="crumbs" aria-label="Навигация">
    <a href="/">Главная</a>
    <span aria-hidden="true">/</span>
    <span>Каталог</span>
  </nav>
  <div class="row between" style="align-items: flex-end; gap: 16px">
    <h1 class="h1">
      <?php if (!empty($landing)): ?>
        <?= e($landing['h1'][0]) ?> <span class="script"><?= e($landing['h1'][1]) ?></span>
      <?php elseif ($val('collection')): ?>
        Коллекция <span class="script">«<?= e($list[0]['collectionName'] ?? $val('collection')) ?>»</span>
      <?php elseif ($val('q')): ?>
        Поиск: <span class="script"><?= e($val('q')) ?></span>
      <?php elseif ($occ): ?>
        Букеты <span class="script"><?= e($occ['title']) ?></span>
      <?php else: ?>
        Каталог
      <?php endif ?>
    </h1>
    <span class="muted"><?= e($shop['promoLine'] ?: 'Перед отправкой пришлём фото именно вашего букета') ?></span>
  </div>
  <?php /* Ряд кнопок листается вбок; на телефоне под ним ползунок, у края — затухание (shop.js) */ ?>
  <div class="scroll-row" data-scroll-row>
    <div class="row chips-row" style="gap: 8px">
      <a href="/catalog" class="chip <?= $allBouquets ? 'on' : '' ?>">Все букеты</a>
      <?php foreach ($sections as $c): ?>
        <?php if (isset($c[2])): ?>
          <a href="<?= e($c[2]) ?>" class="chip"><?= e($c[0]) ?></a>
        <?php else: $on = $isOn($sp, $c[1]) ?>
          <a href="<?= $on ? '/catalog' : e($href([], $c[1])) ?>" class="chip <?= $on ? 'on' : '' ?>"><?= e($c[0]) ?></a>
        <?php endif ?>
      <?php endforeach ?>
    </div>
    <div class="scroll-track" aria-hidden="true" hidden><i></i></div>
  </div>
</section>

<div class="wrap catalog">
  <?php /* На ПК — колонка слева, все группы раскрыты. На телефоне — кнопка «Фильтры», группы сворачиваются */ ?>
  <div class="filters-col" data-filters-col>
    <div class="filters-toggle mobile-only">
      <button type="button" class="btn btn-outline" aria-expanded="false" data-filters-toggle>
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M4 6h16M7 12h10M10 18h4" /></svg>
        Фильтры<?php if ($activeCount > 0): ?><span class="filters-count"><?= $activeCount ?></span><?php endif ?>
      </button>
      <?php if ($activeCount > 0): ?><a href="/catalog" class="small" style="font-weight: 700">Сбросить</a><?php endif ?>
    </div>
    <aside class="filters" data-filters>
      <details class="fgroup"<?= $range ? ' open' : '' ?>>
        <summary>
          <h3>Цена</h3>
          <?php if ($range): ?><span class="fgroup-val"><?= e($range['label']) ?></span><?php endif ?>
        </summary>
        <?php foreach (Occasions::PRICE_RANGES as $r): $on = $val('price') === $r['slug'] ?>
          <a href="<?= $f(['price' => $on ? null : $r['slug']]) ?>" data-keep-scroll class="radio <?= $on ? 'on' : '' ?>"><?= e($r['label']) ?></a>
        <?php endforeach ?>
      </details>
      <details class="fgroup"<?= $occ ? ' open' : '' ?>>
        <summary>
          <h3>Кому</h3>
          <?php if ($occ): ?><span class="fgroup-val"><?= e($occ['label']) ?></span><?php endif ?>
        </summary>
        <div class="chips-wrap">
          <?php foreach (Occasions::LIST as $o): if (!in_array($o['slug'], ['love', 'birthday', 'mom', 'colleague', 'nothing', 'wedding'], true)) continue; $on = $val('occasion') === $o['slug'] ?>
            <a href="<?= $f(['occasion' => $on ? null : $o['slug']]) ?>" data-keep-scroll class="chip chip-sm <?= $on ? 'on' : '' ?>"><?= e($o['label']) ?></a>
          <?php endforeach ?>
        </div>
      </details>
      <?php if ($palette): ?>
        <details class="fgroup"<?= $colors ? ' open' : '' ?>>
          <summary>
            <h3>Цвет</h3>
            <?php if ($colorLabels): ?><span class="fgroup-val"><?= e(implode(', ', $colorLabels)) ?></span><?php endif ?>
          </summary>
          <p class="small muted" style="margin-top: -6px; margin-bottom: 10px">Можно выбрать несколько</p>
          <div class="chips-wrap">
            <?php foreach ($palette as $c): $on = in_array($c['slug'], $colors, true) ?>
              <a href="<?= $f(['color' => Occasions::toggleColor($colors, $c['slug'], $colorList)]) ?>" data-keep-scroll class="swatch-btn <?= $on ? 'on' : '' ?>" title="<?= e($c['label']) ?>" aria-label="<?= e($on ? "{$c['label']}, выбран" : $c['label']) ?>">
                <i class="swatch" style="background: <?= e($c['swatch']) ?>"></i>
              </a>
            <?php endforeach ?>
          </div>
          <?php if ($colorLabels): ?>
            <p class="small" style="margin-top: 10px"><?= e(implode(', ', $colorLabels)) ?> · <a href="<?= $f(['color' => null]) ?>" data-keep-scroll style="font-weight: 700">сбросить</a></p>
          <?php endif ?>
        </details>
      <?php endif ?>
      <details class="fgroup"<?= $val('flower') ? ' open' : '' ?>>
        <summary>
          <h3>Цветок</h3>
          <?php if ($val('flower')): ?><span class="fgroup-val"><?= e($val('flower')) ?></span><?php endif ?>
        </summary>
        <div class="chips-wrap">
          <?php foreach ($flowers as $fl): $on = $val('flower') === $fl['name'] ?>
            <a href="<?= $f(['flower' => $on ? null : $fl['name']]) ?>" data-keep-scroll class="chip chip-sm <?= $on ? 'on' : '' ?>"><?= e($fl['name']) ?></a>
          <?php endforeach ?>
        </div>
      </details>
      <details class="fgroup"<?= $format ? ' open' : '' ?>>
        <summary>
          <h3>Формат</h3>
          <?php if ($format): ?><span class="fgroup-val"><?= e($format['label']) ?></span><?php endif ?>
        </summary>
        <?php foreach (Occasions::FORMATS as $code => $fm): $on = $val('format') === $code ?>
          <a href="<?= $f(['format' => $on ? null : $code]) ?>" data-keep-scroll class="box <?= $on ? 'on' : '' ?>"><?= e($fm['label']) ?></a>
        <?php endforeach ?>
      </details>
      <div class="help">
        <b>Нужна подсказка?</b>
        <span class="small muted">Флорист поможет выбрать в Telegram.</span>
        <a href="<?= e($shop['telegram']) ?>" class="small" style="font-weight: 700" target="_blank" rel="noopener">Написать флористу →</a>
      </div>
      <button type="button" class="btn btn-lg filters-done mobile-only" data-filters-done>Показать <?= count($list) ?> <?= ShopView::bouquets(count($list)) ?></button>
    </aside>
  </div>

  <div id="catalog-list" class="stack" style="gap: 28px; min-width: 0">
    <div class="catalog-bar">
      <span>Сегодня по Минску из ближайшего магазина. Доставка от <?= e($minDelivery) ?>, от <?= e(Money::format($delivery['freeFromKop'])) ?> <span class="gift">бесплатно</span>.</span>
      <label class="sort">
        Сортировка
        <select class="input" data-sort='<?= e(json_encode($sortHrefs, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) ?>'>
          <option value=""<?= $sort === '' ? ' selected' : '' ?>>Сначала популярные</option>
          <option value="cheap"<?= $sort === 'cheap' ? ' selected' : '' ?>>Сначала дешевле</option>
          <option value="expensive"<?= $sort === 'expensive' ? ' selected' : '' ?>>Сначала дороже</option>
        </select>
      </label>
    </div>

    <?php if ($list): ?>
      <div class="grid grid-3">
        <?php foreach (array_slice($list, 0, $shown) as $b) echo View::render('shop/_card', ['b' => $b, 'note' => "Доставка от $minDelivery"]) ?>
      </div>
      <?php if (count($list) > $shown): ?>
        <div class="more">
          <a href="<?= $f(['n' => (string) ($shown + $pageSize)]) ?>" data-keep-scroll class="btn btn-outline btn-lg">Показать ещё</a>
          <span class="small muted">Показано <?= $shown ?> из <?= count($list) ?></span>
        </div>
      <?php endif ?>
    <?php elseif ($val('q')): ?>
      <div class="empty panel">
        <h2 class="h2">По запросу «<?= e($val('q')) ?>» ничего нет</h2>
        <p class="muted">Проверьте написание или посмотрите похожее. Флорист соберёт букет и на заказ.</p>
        <div class="row" style="justify-content: center">
          <a href="<?= e($shop['telegram']) ?>" class="btn" target="_blank" rel="noopener">Написать флористу в Telegram</a>
          <a href="/catalog">Смотреть весь каталог →</a>
        </div>
      </div>
    <?php else: ?>
      <div class="empty panel">
        <h2 class="h2">Под эти фильтры букетов нет</h2>
        <?php if ($activeFilters): ?>
          <p class="muted">Попробуйте убрать один из фильтров: <?= e(implode(' или ', array_map(fn ($x) => "«{$x}»", $activeFilters))) ?>.</p>
        <?php endif ?>
        <div class="row" style="justify-content: center">
          <a href="/catalog" class="btn">Сбросить фильтры</a>
          <?php if ($range): ?><a href="<?= $f(['price' => null]) ?>">Показать без фильтра по цене →</a><?php endif ?>
        </div>
      </div>
    <?php endif ?>

    <?php if ($addons): ?>
      <div class="addons">
        <h2>Добавить к букету</h2>
        <div class="addons-grid">
          <?php foreach ($addons as $a): ?>
            <div class="addon">
              <b><?= e($a['name']) ?></b>
              <?php if ($a['priceKop'] === 0): ?><span class="gift">В подарок</span><?php else: ?><span class="price" style="font-size: 18px"><?= ShopView::byn($a['priceKop']) ?></span><?php endif ?>
            </div>
          <?php endforeach ?>
        </div>
      </div>
    <?php endif ?>

    <div class="band">
      <div class="stack" style="flex: 1 1 320px; gap: 10px">
        <h2>Тот самый букет ещё <span class="script">не придуман?</span></h2>
        <p>Расскажите флористу, кому и по какому поводу. Соберём и пришлём фото до отправки.</p>
      </div>
      <a href="<?= e($shop['telegram']) ?>" class="btn btn-pink btn-lg" target="_blank" rel="noopener">Написать флористу</a>
    </div>
  </div>
</div>

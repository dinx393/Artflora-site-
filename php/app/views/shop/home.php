<?php
// Главная (src/app/(shop)/page.tsx). Переменные из routes/shop.php.
// Вырез в вишнёвом блоке: справа сверху встаёт «Букет дня», снизу — фото.
$heroNotch = 'M706 336C706 349.26 695.26 360 682 360H553.82C540.57 360 529.82 370.75 529.82 384V564C529.82 577.26 519.08 588 505.82 588H24C10.75 588 0 577.26 0 564V24C0 10.75 10.75 0 24 0H682C695.26 0 706 10.75 706 24V336Z';
// «Огненный экзотический букет»: первое слово — рукописным, остальное — обычным шрифтом.
$splitName = function (string $name): array {
    $clean = trim(preg_replace('/[«»"]/u', '', $name));
    $parts = preg_split('/\s+/u', $clean);
    if (count($parts) < 2 || !preg_match('/^\p{L}+$/u', $parts[0])) return ['', $clean];
    return [$parts[0], implode(' ', array_slice($parts, 1))];
};
$shopNames = implode(', ', array_map(fn ($s) => $s['name'], $delivery['shops']));
// «Независимости 168/1 · Яна Чечота 7» — адреса магазинов без «пр.»/«ул.» и запятых
$pickupAddresses = array_map(fn ($s) => ShopView::mapLink($s, preg_replace(['/^(пр|ул)\.\s*/u', '/,\s*/u'], ['', ' '], $s['address']), 'nowrap map-link'), $delivery['shops']);
$heroFacts = [
    ['Фото', 'букета перед доставкой'],
    ['24 часа', 'гарантия свежести'],
    ['Кризал', 'подкормка в подарок'],
    ['−' . $delivery['pickupDiscountPct'] . '%', 'самовывоз: ' . $shopNames],
];
$cards = [
    ['shield', str_replace('Гарантия свежести', 'Гарантия', ShopView::GUARANTEE_TITLE), e(ShopView::GUARANTEE_TEXT), true],
    ['clock', 'В день заказа', e('Везём из ближайшего магазина, доставка от ' . Money::format(Delivery::minKop($delivery)) . '. Самовывоз −' . $delivery['pickupDiscountPct'] . '%.'), false],
    ['camera', 'Фото до отправки', 'Согласуем букет с вами, прежде чем курьер выедет.', false],
    ['card', 'Открытка и подкормка', 'Кладём <span class="gift">в подарок</span> к каждому букету.', false],
];
?>
<section class="wrap hero-wrap">
  <div class="hero">
    <div class="hero-main">
      <svg class="hero-shape" viewBox="0 0 706 588" preserveAspectRatio="none" aria-hidden="true"><path d="<?= $heroNotch ?>" /></svg>
      <div class="hero-text">
        <span class="eyebrow desktop-only">Сеть салонов цветов: Уручье, Малиновка</span>
        <h1>Букетов много,<br> а запомнят <span class="script">ваш</span></h1>
        <?php /* Главное за 3 секунды: крупное слово + подпись (вариант Б, решение владельца 2026-10-23) */ ?>
        <ul class="hero-facts">
          <?php foreach ($heroFacts as [$big, $note]): ?>
            <li><b><?= e($big) ?></b><span><?= e($note) ?></span></li>
          <?php endforeach ?>
        </ul>
        <div class="hero-ways">
          <p class="hero-delivery"><?= Icons::get('truck') ?>Доставка по Минску и Минскому району</p>
          <p class="hero-delivery"><?= Icons::get('pin') ?><span>Самовывоз: <?= implode(' · ', $pickupAddresses) ?></span></p>
        </div>
      </div>
      <div class="hero-actions">
        <a href="/catalog" class="btn btn-pink">Выбрать букет</a>
        <a href="<?= e($shop['telegram']) ?>" class="btn btn-on-cherry" target="_blank" rel="noopener">Написать в Telegram</a>
      </div>
    </div>
    <?php if ($ofDay): ?>
      <a href="/bouquet/<?= e(rawurlencode($ofDay['slug'])) ?>" class="hero-day">
        <img src="<?= e($ofDay['photos'][0]) ?>" alt="<?= e($ofDay['name']) ?>">
        <span class="hero-day-body">
          <span class="eyebrow muted">Букет дня · собран сегодня</span>
          <span class="hero-day-name"><?= e($ofDay['name']) ?></span>
          <span class="price"><?= ShopView::byn($ofDay['priceKop']) ?></span>
          <span class="hero-day-more">Смотреть букет →</span>
        </span>
      </a>
    <?php endif ?>
    <a href="/catalog" class="hero-tile <?= $ofDay ? '' : 'hero-tile-full' ?>">
      <span class="hero-tile-photos n<?= count($tilePhotos) ?>">
        <?php foreach ($tilePhotos as $i => $p): ?><img src="<?= e($p) ?>" alt="<?= $i === 0 ? 'Букеты Artflora.by' : '' ?>"><?php endforeach ?>
      </span>
      <span class="hero-chip">Весь каталог ↗</span>
    </a>
  </div>
</section>

<section class="section" id="occasions">
  <h2 class="h2 sec-title">Кому сегодня <span class="script">дарим?</span></h2>
  <?php /* На телефоне ряд листается вбок, под ним ползунок (shop.js) */ ?>
  <div class="scroll-row" data-scroll-row>
    <div class="occasions" data-scroll-items>
      <?php foreach (Occasions::LIST as $o): if ($o['slug'] === 'exotic') continue ?>
        <a href="<?= e(Seo::catalogUrl(['occasion' => $o['slug']])) ?>" class="occasion"><span><?= Icons::occasion($o['slug']) ?></span><?= e($o['label']) ?></a>
      <?php endforeach ?>
    </div>
    <div class="scroll-track" aria-hidden="true" hidden><i></i></div>
  </div>
</section>

<?php if ($all): ?>
  <section class="section">
    <div class="row between sec-title" style="align-items: flex-end; gap: 16px">
      <h2 class="h2">Хиты <span class="script">этой недели</span></h2>
      <div class="row chips-row" style="gap: 8px">
        <a href="/#hits" class="chip <?= $range ? '' : 'on' ?>" data-keep-scroll>Все</a>
        <?php foreach (Occasions::PRICE_RANGES as $r): ?>
          <a href="<?= $price === $r['slug'] ? '/#hits' : '/?price=' . e($r['slug']) . '#hits' ?>" class="chip <?= $price === $r['slug'] ? 'on' : '' ?>" data-keep-scroll><?= e($r['label']) ?></a>
        <?php endforeach ?>
      </div>
    </div>
    <div class="grid grid-4" id="hits">
      <?php foreach ($hits as $b) echo View::render('shop/_card', ['b' => $b]) ?>
    </div>
    <?php if (!$hits): ?>
      <p class="muted">В этом диапазоне хитов нет. <a href="/catalog">Смотреть весь каталог →</a></p>
    <?php endif ?>
    <div class="more"><a href="/catalog" class="btn btn-outline btn-lg">Смотреть весь каталог →</a></div>
  </section>
<?php endif ?>

<?php if ($exclusive): ?>
  <section class="section">
    <div class="stack sec-title" style="gap: 10px">
      <span class="eyebrow muted">Только на этой неделе</span>
      <h2 class="h2">Эксклюзив <span class="script">этой недели</span></h2>
    </div>
    <div class="stories">
      <?php foreach ($exclusive as $b):
          [$script, $text] = $splitName($b['name']);
          $size = array_values(array_filter($b['sizes'], fn ($s) => $s['code'] === $b['defaultSize']))[0] ?? null; ?>
        <a href="/bouquet/<?= e(rawurlencode($b['slug'])) ?>" class="story">
          <img src="<?= e($b['photos'][0]) ?>" alt="<?= e($b['name']) ?>" loading="lazy">
          <span class="story-shade"></span>
          <span class="story-title">
            <?php if ($script !== ''): ?><span class="story-script"><?= e($script) ?></span><?php endif ?>
            <span class="story-text"><?= e($text) ?></span>
          </span>
          <span class="story-price"><?= ShopView::byn($b['priceKop']) ?></span>
          <?php if ($size && $size['note'] !== ''): ?><span class="story-note"><?= e($size['note']) ?></span><?php endif ?>
        </a>
      <?php endforeach ?>
    </div>
  </section>
<?php endif ?>

<section class="section">
  <div class="band">
    <div class="stack" style="flex: 1 1 420px; gap: 12px">
      <h2>Не нашли свой? Соберём <span class="script">вместе.</span></h2>
      <p>Напишите повод, любимые цвета и бюджет. Флорист предложит состав и пришлёт фото до отправки.</p>
    </div>
    <a href="<?= e($shop['telegram']) ?>" class="btn btn-pink btn-lg" target="_blank" rel="noopener">Написать флористу</a>
  </div>
</section>

<section class="section">
  <h2 class="h2 sec-title">Важно <span class="script">знать</span></h2>
  <div class="cards-4">
    <?php foreach ($cards as [$icon, $h, $text, $accent]): ?>
      <div class="info-card<?= $accent ? ' info-accent' : '' ?>">
        <i><?= Icons::get($icon) ?></i>
        <h3><?= e($h) ?></h3>
        <p><?= $text ?></p>
      </div>
    <?php endforeach ?>
  </div>
</section>

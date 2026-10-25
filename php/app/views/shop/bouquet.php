<?php
// Страница букета (src/app/(shop)/bouquet/[slug]/page.tsx + Gallery, BuyBox, DeliveryEstimate).
// Выбор размера, допы, «В корзину», фото и район доставки оживляет shop.js.
$views = ['Общий вид', 'Ближе', 'Сбоку', 'Детали'];
$photos = $b['photos'];
$pickupPct = $delivery['pickupDiscountPct'];
$firstOcc = $b['occasions'][0] ?? null;
$q = mb_strpos($b['name'], '«');
[$nameMain, $nameQuote] = $q === false ? [$b['name'], ''] : [mb_substr($b['name'], 0, $q), mb_substr($b['name'], $q)];
// «Розы» в тегах, если в составе есть розы
$tags = [];
foreach (['роз' => 'Розы', 'хризантем' => 'Хризантемы', 'пион' => 'Пионы'] as $stem => $tag) {
    if (array_filter($b['flowerNames'], fn ($n) => mb_stripos($n, $stem) !== false)) $tags[] = $tag;
}
$pickup = fn (int $kop) => Money::format($kop - Money::percentOff($kop, $pickupPct));
$def = array_values(array_filter($b['sizes'], fn ($s) => $s['code'] === $b['defaultSize']))[0];
$shopNames = array_column($delivery['shops'], 'name');
$link = fn (array $x) => '/bouquet/' . rawurlencode($x['slug']);
?>
<div class="wrap stack" style="padding-top: 20px; padding-bottom: 64px; gap: 40px">
  <nav class="small muted row" style="gap: 8px">
    <a href="/" class="nolink muted">Главная</a>
    <span>/</span>
    <a href="/catalog" class="nolink muted">Каталог</a>
    <?php if ($firstOcc): ?>
      <span>/</span>
      <a href="<?= e(Seo::catalogUrl(['occasion' => $firstOcc])) ?>" class="nolink muted"><?= e(Occasions::label($firstOcc)) ?></a>
    <?php endif ?>
    <span>/</span>
    <span style="color: var(--cherry)"><?= e($b['name']) ?></span>
  </nav>

  <div class="product">
    <div class="gallery" data-gallery>
      <div class="gallery-main">
        <img src="<?= e($photos[0]) ?>" alt="<?= e($b['name']) ?>" data-gallery-main>
        <?= ShopView::badges($b, 'left: 16px; top: 16px') ?>
        <span class="badge" style="left: 16px; top: auto; bottom: 16px; font-weight: 400" data-gallery-label><?= e($views[0]) ?></span>
      </div>
      <?php if (count($photos) > 1): ?>
        <div class="thumbs">
          <?php foreach ($photos as $n => $p): $label = $views[$n] ?? 'Фото ' . ($n + 1) ?>
            <button type="button" class="<?= $n === 0 ? 'on' : '' ?>" aria-label="<?= e($label) ?>" data-photo="<?= e($p) ?>" data-label="<?= e($label) ?>"><img src="<?= e($p) ?>" alt=""></button>
          <?php endforeach ?>
        </div>
      <?php endif ?>
      <div class="small muted">Перед отправкой пришлём фото именно вашего букета.</div>
    </div>

    <div class="buy" data-buybox data-bouquet="<?= e($b['id']) ?>" data-size="<?= e($b['defaultSize']) ?>">
      <div class="row" style="gap: 6px">
        <?php foreach ($b['occasions'] as $o): ?>
          <a href="<?= e(Seo::catalogUrl(['occasion' => $o])) ?>" class="tag"><?= e(Occasions::label($o)) ?></a>
        <?php endforeach ?>
        <?php foreach ($tags as $t): ?>
          <a href="/catalog?q=<?= e(rawurlencode($t)) ?>" class="tag"><?= e($t) ?></a>
        <?php endforeach ?>
      </div>
      <h1><?= e($nameMain) ?><?php if ($nameQuote !== ''): ?><span class="script"><?= e($nameQuote) ?></span><?php endif ?></h1>

      <div class="row" style="align-items: baseline; gap: 14px">
        <span class="price" style="font-size: 40px" data-price><?= ShopView::byn($def['priceKop']) ?></span>
        <span class="small muted" data-pickup>Самовывоз: <?= e($pickup($def['priceKop'])) ?></span>
      </div>

      <?php if (count($b['sizes']) > 1): ?>
        <div class="stack" style="gap: 8px">
          <div class="label">Размер</div>
          <div class="size-grid">
            <?php foreach ($b['sizes'] as $s): ?>
              <button type="button" class="opt <?= $s['code'] === $b['defaultSize'] ? 'on' : '' ?>" data-size-opt="<?= e($s['code']) ?>"
                data-price-html="<?= e(ShopView::byn($s['priceKop'])) ?>" data-pickup-text="<?= e('Самовывоз: ' . $pickup($s['priceKop'])) ?>">
                <b><?= e($s['code']) ?> · <?= e(Money::format($s['priceKop'])) ?></b>
                <?php if ($s['note'] !== ''): ?><span class="note"><?= e($s['note']) ?></span><?php endif ?>
              </button>
            <?php endforeach ?>
          </div>
        </div>
      <?php endif ?>

      <?php /* Сначала размеры, потом этот же состав в другом виде; если других видов нет — похожие букеты */ ?>
      <?php if (count($siblings) > 1): ?>
        <div class="stack">
          <div class="row between" style="align-items: baseline">
            <span class="label">Этот же состав в другом виде</span>
            <?php if ($b['collectionSlug']): ?>
              <a href="/catalog?collection=<?= e(rawurlencode($b['collectionSlug'])) ?>" class="small" style="font-weight: 700">Вся коллекция «<?= e($b['collectionName']) ?>» →</a>
            <?php endif ?>
          </div>
          <div class="forms">
            <?php foreach ($siblings as $s): $inForm = Occasions::FORMATS[$s['format']]['inForm'] ?>
              <a href="<?= e($link($s)) ?>" class="<?= $s['id'] === $b['id'] ? 'on' : '' ?>">
                <span class="ph"><img src="<?= e($s['photos'][0]) ?>" alt="<?= e($inForm) ?>"></span>
                <span class="t stack" style="gap: 2px">
                  <b style="font-size: 14px"><?= e($inForm) ?></b>
                  <?php if ($s['id'] === $b['id']): ?>
                    <span style="color: var(--muted); font-weight: 700">Вы смотрите</span>
                  <?php else: ?>
                    <span class="muted"><?= e(Money::format($s['priceKop'])) ?></span>
                  <?php endif ?>
                </span>
              </a>
            <?php endforeach ?>
          </div>
        </div>
      <?php elseif ($similar): ?>
        <div class="stack">
          <div class="row between" style="align-items: baseline">
            <span class="label">Похожие букеты</span>
            <a href="/catalog" class="small" style="font-weight: 700">Смотреть все →</a>
          </div>
          <div class="forms">
            <?php foreach (array_slice($similar, 0, 3) as $s): ?>
              <a href="<?= e($link($s)) ?>">
                <span class="ph"><img src="<?= e($s['photos'][0]) ?>" alt="<?= e($s['name']) ?>"></span>
                <span class="t stack" style="gap: 2px">
                  <b class="forms-name"><?= e($s['name']) ?></b>
                  <span class="price" style="font-size: 15px"><?= ShopView::byn($s['priceKop']) ?></span>
                </span>
              </a>
            <?php endforeach ?>
          </div>
        </div>
      <?php endif ?>

      <div class="stack" style="gap: 8px">
        <div class="label">К букету</div>
        <div class="row" style="gap: 8px; align-items: stretch">
          <div class="opt on" style="flex: 1 1 150px; background: var(--blush); cursor: default">
            <span style="font-weight: 700">Открытка с текстом</span>
            <span class="ok">Добавлена · бесплатно</span>
          </div>
          <?php foreach ($addons as $a): if ($a['included']) continue ?>
            <button type="button" class="opt" style="flex: 1 1 150px" data-addon="<?= e($a['id']) ?>" data-price-text="<?= e(Money::format($a['priceKop'])) ?>">
              <span style="font-weight: 700"><?= e($a['name']) ?></span>
              <span class="note"><?= e(Money::format($a['priceKop'])) ?></span>
            </button>
          <?php endforeach ?>
          <?php foreach ($addons as $a): if (!$a['included']) continue ?>
            <div class="opt" style="flex: 1 1 150px; border-style: dashed; cursor: default">
              <span style="font-weight: 700"><?= e($a['name']) ?></span>
              <span class="ok">Уже в комплекте</span>
            </div>
          <?php endforeach ?>
        </div>
        <label class="field">
          <span>Текст открытки</span>
          <textarea class="input" rows="2" maxlength="500" placeholder="Мама, с днём рождения! Люблю тебя" data-card-text></textarea>
        </label>
      </div>

      <button type="button" class="btn btn-lg btn-block" data-add-big>Добавить в корзину</button>
      <div class="row between card" data-added hidden>
        <span class="small">Букет в корзине. Оформим доставку?</span>
        <a href="/cart" style="font-weight: 800; text-decoration: none">Оформить заказ →</a>
      </div>

      <?= ShopView::guarantee() ?>

      <div class="notice">
        <span style="color: var(--olive); margin-top: 1px"><?= Icons::get('leaf') ?></span>
        <span><?= e(ShopView::SUBSTITUTION_NOTE) ?></span>
      </div>
      <a href="<?= e($shop['telegram']) ?>" style="align-self: flex-start; font-weight: 700; font-size: 15px" target="_blank" rel="noopener">Есть вопрос? Напишите флористу в Telegram →</a>

      <?php /* «Куда доставить?»: цена доставки по району */ ?>
      <div class="card stack" data-delivery-estimate>
        <div class="row between" style="align-items: baseline">
          <span class="label">Куда доставить?</span>
          <span style="font-family: var(--serif); font-size: 24px; font-weight: 700" data-delivery-price hidden></span>
        </div>
        <select class="input" aria-label="Район доставки" data-district>
          <option value="">Выберите район</option>
          <?php foreach ($districts as $d): $free = $d['priceKop'] === 0 ?>
            <option value="<?= e($d['name']) ?>" data-label="<?= e($free ? 'Доставка бесплатно' : 'Доставка ' . Money::format($d['priceKop'])) ?>" data-shop="<?= e($d['shopName']) ?>">
              <?= e($d['name']) ?> — <?= e($free ? 'бесплатно' : Money::format($d['priceKop'])) ?>
            </option>
          <?php endforeach ?>
        </select>
        <div class="small muted" data-delivery-shop hidden></div>
        <div class="small muted">Возим по Минску и за МКАД. Бесплатно от <?= e(Money::format($delivery['freeFromKop'])) ?>. Привезём <?= e(Text::fromHours($shop['leadHours'])) ?>.</div>
      </div>

      <div class="acc">
        <details open>
          <summary>Состав</summary>
          <div class="stack" style="gap: 6px">
            <?php if ($b['description'] !== ''): ?><p><?= e($b['description']) ?></p><?php endif ?>
            <?php foreach ($b['sizes'] as $s): ?>
              <p><b><?= e($s['code']) ?>:</b> <?= e($s['composition']) ?></p>
            <?php endforeach ?>
          </div>
        </details>
        <details>
          <summary>Как ухаживать</summary>
          <div>Подрежьте стебли наискось на 1–2 см, налейте прохладную воду и добавьте подкормку из пакетика. Меняйте воду через день и держите букет подальше от батареи и прямого солнца. Если в первые сутки с букетом что-то случится, напишите нам: поможем с заменой.</div>
        </details>
        <details>
          <summary>Доставка и оплата</summary>
          <div>Доставляем по Минску и району из ближайшего магазина: <?= e(implode(' или ', $shopNames)) ?>. Самовывоз со скидкой <?= e($pickupPct) ?>%. Оплата через ЕРИП, картой через WebPay или безналичным расчётом для организаций.</div>
        </details>
      </div>
    </div>
  </div>

  <div class="perks" style="padding: 28px">
    <div><b style="font-size: 20px">Фото букета перед отправкой</b><span class="muted">Пришлём в Viber или Telegram</span></div>
    <div><b style="font-size: 20px">Открытка бесплатно</b><span class="muted">Напишем ваш текст от руки</span></div>
    <div><b style="font-size: 20px">Сегодня до 21:00</b><span class="muted">Доставка рядом с магазином</span></div>
    <div><b style="font-size: 20px">Самовывоз −<?= e($pickupPct) ?>%</b><span class="muted"><?= e(implode(' и ', $shopNames)) ?></span></div>
  </div>

  <?php if ($similar): ?>
    <section class="stack" style="gap: 20px">
      <h2 class="h2">Похожие <span class="script">букеты</span></h2>
      <div class="grid grid-4">
        <?php foreach ($similar as $x) echo View::render('shop/_card', ['b' => $x]) ?>
      </div>
    </section>
  <?php endif ?>
</div>

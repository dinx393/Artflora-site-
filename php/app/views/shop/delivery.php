<?php
// Доставка и оплата (src/app/(shop)/delivery/page.tsx). Переменные: $shop, $d (настройки доставки)
$last = count($d['zones']) - 1;
?>
<div class="section stack" style="gap: 40px">
  <h1 class="h2">Доставка <span class="script">и оплата</span></h1>
  <div class="cards-4">
    <div class="info-card">
      <h3>Сегодня до 21:00</h3>
      <p>Везём из ближайшего к вам магазина: Уручье или Малиновка. Заказ на сегодня оформляйте хотя бы за <?= e($shop['leadHours']) ?> ч до нужного времени.</p>
    </div>
    <div class="info-card">
      <h3>Бесплатно от <?= e(Money::format($d['freeFromKop'])) ?></h3>
      <p>Если букеты в заказе стоят <?= e(Money::format($d['freeFromKop'])) ?> или больше, доставка по Минску <span class="gift">в подарок</span>.</p>
    </div>
    <div class="info-card">
      <h3>Самовывоз −<?= e($d['pickupDiscountPct']) ?>%</h3>
      <p><?= implode('. ', array_map(fn ($s) => e($s['name']) . ', ' . ShopView::mapLink($s, $s['address'], 'map-link') . ', ' . e($s['hours']), $d['shops'])) ?>.</p>
    </div>
    <div class="info-card info-accent">
      <h3><?= e(ShopView::GUARANTEE_TITLE) ?></h3>
      <p><?= e(ShopView::GUARANTEE_TEXT) ?></p>
    </div>
    <div class="info-card">
      <h3>Фото до отправки</h3>
      <p>Пришлём фото готового букета, и курьер выедет только после вашего «да».</p>
    </div>
  </div>

  <div class="panel stack" style="gap: 14px">
    <h2 style="font-size: 30px">Сколько стоит доставка</h2>
    <div class="stack" style="gap: 0">
      <?php foreach ($d['zones'] as $i => $z): ?>
        <div class="sum-row zone-row">
          <span><?= $i === $last ? 'Дальше, в пределах МКАД' : e("До {$z['maxKm']} км от магазина") ?></span>
          <b class="price" style="font-size: 22px"><?= ShopView::byn((int) $z['priceKop']) ?></b>
        </div>
      <?php endforeach ?>
      <div class="sum-row zone-row">
        <span>За МКАД</span>
        <b class="price" style="font-size: 22px"><?= ShopView::byn((int) $d['outsideMkadKop']) ?></b>
      </div>
      <?php foreach ($d['areas'] ?? [] as $a): if (!empty($a['noDelivery']) || count($a['points']) < 3) continue ?>
        <div class="sum-row zone-row">
          <span><i class="zone-dot" style="background: <?= e($a['color']) ?>; margin-right: 8px; vertical-align: -2px"></i><?= e($a['name']) ?> (зона на карте ниже)</span>
          <b class="price" style="font-size: 22px"><?= ShopView::byn((int) $a['priceKop']) ?></b>
        </div>
      <?php endforeach ?>
      <div class="sum-row zone-row">
        <span>Как далеко возим</span>
        <b>до <?= e($d['maxOutsideMkadKm']) ?> км за МКАД</b>
      </div>
    </div>
    <p class="small muted">Выберите район в корзине, и цена появится сразу. Нет вашего района в списке? Напишите нам, договоримся.</p>
  </div>

  <?= View::render('partials/zones-map', ['delivery' => $d, 'districts' => [], 'open' => true]) ?>

  <div class="stack" style="gap: 14px">
    <h2 style="font-size: 30px">Оплата</h2>
    <p>Через ЕРИП или картой через WebPay. В ЕРИП: код услуги <?= e($shop['eripCode']) ?> или путь <?= e($shop['eripPath']) ?>, дальше номер телефона заказчика и сумма. Для WebPay администратор свяжется с вами и пришлёт ссылку для оплаты.</p>
    <p>Для организаций — безналичный расчёт. В корзине выберите оплату «Безнал для организаций» и вставьте реквизиты компании одним текстом. Мы выставим счёт на организацию.</p>
  </div>

  <div class="stack" style="gap: 14px" id="vozvrat">
    <h2 style="font-size: 30px">Обмен и возврат</h2>
    <p>Живые цветы надлежащего качества обмену и возврату не подлежат (Перечень к постановлению Совета Министров Республики Беларусь от 14.06.2002 № 778). Претензии по качеству цветов принимаем при получении заказа или в течение 2 часов после покупки: позвоните по телефону <a href="tel:+<?= e(preg_replace('/\D/', '', $shop['phone'])) ?>"><?= e($shop['phone']) ?></a> или напишите нам.</p>
    <div class="row">
      <a href="/catalog" class="btn btn-lg">Выбрать букет</a>
      <a href="<?= e($shop['telegram']) ?>" class="btn btn-lg btn-outline" target="_blank" rel="noopener">Спросить в Telegram</a>
    </div>
  </div>
</div>

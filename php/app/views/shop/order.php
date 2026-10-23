<?php
// «Спасибо! Заказ принят» (повторяет src/app/(shop)/order/[number]/page.tsx). Переменные: $order, $shop, $pickupShop
$pay = ['CARD' => 'картой, ссылку пришлём', 'ERIP' => 'через ЕРИП, код пришлём', 'VIBER_LINK' => 'ссылкой в Viber после фото'];
$months = ['января', 'февраля', 'марта', 'апреля', 'мая', 'июня', 'июля', 'августа', 'сентября', 'октября', 'ноября', 'декабря'];
$t = strtotime($order['deliveryDate'] . ' 00:00:00 UTC');
$day = (int) gmdate('j', $t) . ' ' . $months[(int) gmdate('n', $t) - 1];
$byn = fn (int $kop) => e(preg_replace('/ BYN$/', '', Money::format($kop))) . '<small class="cur">BYN</small>';
$o = $order;
?>
<div class="section row" style="gap: 40px; align-items: flex-start">
  <div class="stack" style="flex: 1 1 420px; gap: 20px">
    <h1 class="h2" style="font-size: 56px">Спасибо! Заказ <span class="script">принят</span></h1>
    <p class="muted" style="font-size: 18px">
      Номер заказа <?= e($o['number']) ?>. Мы позвоним или напишем на <?= e(Text::phone($o['customerPhone'])) ?>. Что будет дальше:
    </p>
    <ol class="stack" style="gap: 14px; padding-left: 20px; margin: 0">
      <li>
        <b>Флорист соберёт букет</b>
        <div class="muted"><?= e($o['deliverySlot'] ? "К выбранному времени: $day, {$o['deliverySlot']}" : "На $day, время согласуем с получателем") ?></div>
      </li>
      <li>
        <b>Пришлём фото в Viber</b>
        <div class="muted">Если что-то не так, поправим до отправки</div>
      </li>
      <li>
        <?php if ($o['deliveryMethod'] === 'DELIVERY' && $o['addressUnknown']): ?>
          <b>Позвоним получателю и уточним адрес</b>
          <div class="muted">Потом пришлём вам доплату за доставку, и курьер привезёт букет</div>
        <?php elseif ($o['deliveryMethod'] === 'DELIVERY'): ?>
          <b>Курьер позвонит получателю</b>
          <div class="muted">И привезёт букет по адресу</div>
        <?php else: ?>
          <b>Заберите букет в магазине</b>
          <div class="muted"><?= $pickupShop ? e($pickupShop['name']) . ', ' . ShopView::mapLink($pickupShop, $pickupShop['address'], 'map-link') : e($o['shop']) ?></div>
        <?php endif ?>
      </li>
    </ol>
    <div class="row">
      <a href="<?= e($shop['telegram']) ?>" class="btn btn-lg" target="_blank" rel="noopener">Открыть чат в Telegram</a>
      <a href="/" class="btn btn-lg btn-outline">На главную</a>
    </div>
  </div>
  <div class="card stack" style="flex: 1 1 340px; gap: 10px">
    <h2 style="font-size: 28px">Заказ № <?= e($o['number']) ?></h2>
    <?php if ($o['deliveryMethod'] === 'DELIVERY'): ?>
      <p class="small muted">Получатель: <?= e(implode(', ', array_filter([$o['recipientName'] ?: $o['customerName'], $o['address']]))) ?></p>
    <?php endif ?>
    <?php foreach ($o['items'] as $i): ?>
      <div class="sum-row">
        <span><?= e($i['name'] . ($i['size'] ? ", {$i['size']}" : '') . ($i['qty'] > 1 ? " × {$i['qty']}" : '')) ?></span>
        <span><?= e(Money::format($i['priceKop'] * $i['qty'])) ?></span>
      </div>
    <?php endforeach ?>
    <div class="sum-row"><span>Открытка и подкормка</span><span style="color: var(--olive); font-weight: 700">в подарок</span></div>
    <?php if ($o['discountKop'] > 0): ?>
      <div class="sum-row"><span>Скидка за самовывоз</span><span><?= e(Money::format(-$o['discountKop'])) ?></span></div>
    <?php endif ?>
    <?php if ($o['deliveryMethod'] === 'DELIVERY'): ?>
      <div class="sum-row">
        <span>Доставка<?= $o['deliveryZone'] ? e(", зона {$o['deliveryZone']}") : '' ?></span>
        <span><?= e($o['addressUnknown'] && !$o['deliveryKop'] ? 'доплата после уточнения адреса' : ($o['deliveryKop'] ? Money::format($o['deliveryKop']) : 'бесплатно')) ?></span>
      </div>
    <?php endif ?>
    <div class="sum-row"><span>Оплата</span><span><?= e($pay[$o['paymentMethod']] ?? '') ?></span></div>
    <div class="sum-row" style="border-top: 1px solid var(--line); padding-top: 10px; align-items: baseline">
      <b>Итого</b><span class="sum-total"><?= $byn($o['totalKop']) ?></span>
    </div>
  </div>
</div>

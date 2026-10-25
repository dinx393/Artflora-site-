<?php
// Заказ = src/app/admin/(panel)/orders/[id]/page.tsx
$tel = fn (string $p) => '<a href="tel:' . e($p) . '">' . e(Text::phone($p)) . '</a>';
$created = date('d.m.Y, H:i:s', strtotime($o['createdAt']));
?>
<a href="/admin/orders" class="small">← Все заказы</a>
<div class="row between">
  <h1>Заказ № <?= e($o['number']) ?></h1>
  <span class="pill" style="font-size: 15px"><?= e(ADMIN_STATUS_LABEL[$o['status']]) ?></span>
</div>
<div class="admin-grid">
  <div class="stack" style="gap: 16px">
    <div class="card stack" style="gap: 8px">
      <span class="label">Что собрать</span>
      <?php foreach ($o['items'] as $i): ?>
        <div class="stack" style="gap: 2px; padding-bottom: 8px; border-bottom: 1px solid var(--line-soft)">
          <div class="sum-row"><b><?= e($i['name'] . ($i['size'] ? ", {$i['size']}" : '')) ?> × <?= $i['qty'] ?></b><span><?= e(Money::format($i['priceKop'] * $i['qty'])) ?></span></div>
          <?php if ($i['composition']): ?><span class="small muted"><?= e($i['composition']) ?></span><?php endif ?>
        </div>
      <?php endforeach ?>
      <div class="sum-row"><b>🎁 В подарок: <?= e(mb_strtolower(OrderEmail::GIFT)) ?></b></div>
      <div class="sum-row"><span>Открытка</span><span class="small"><?= $o['cardText'] ? e("«{$o['cardText']}»") : 'без текста' ?></span></div>
    </div>
    <div class="card stack" style="gap: 6px">
      <span class="label"><?= $o['deliveryMethod'] === 'PICKUP' ? 'Самовывоз' : 'Доставка' ?></span>
      <span><b><?= e(admin_date($o['deliveryDate'], true)) ?></b>, <?= e($o['deliverySlot'] ?: 'время уточнить') ?></span>
      <?php if ($o['deliveryMethod'] === 'DELIVERY'): ?>
        <?php if ($o['addressUnknown']): ?>
          <span class="pill" style="align-self: flex-start; background: var(--lavender)">Адрес и время уточнить у получателя</span>
          <form method="post" action="/admin/orders/address" class="stack" style="gap: 8px; margin-top: 6px">
            <?= Auth::csrfField() ?>
            <input type="hidden" name="id" value="<?= $o['id'] ?>">
            <label class="field">
              <span>Адрес, который назвал получатель</span>
              <input class="input" name="address" value="<?= e($o['address']) ?>" placeholder="Улица, дом, квартира">
            </label>
            <div class="row" style="gap: 8px; align-items: flex-end">
              <label class="field">
                <span>Время</span>
                <select class="input" name="slot">
                  <option value="">не знаем</option>
                  <?php foreach (array_unique([...Slots::ALL, ...($o['deliverySlot'] ? [$o['deliverySlot']] : [])]) as $s): ?><option value="<?= e($s) ?>" <?= $o['deliverySlot'] === $s ? 'selected' : '' ?>><?= e($s) ?></option><?php endforeach ?>
                </select>
              </label>
              <label class="field" style="flex: 1 1 120px">
                <span>Доплата за доставку, BYN</span>
                <input class="input" name="delivery" inputmode="decimal" value="<?= e(rtrim(rtrim(number_format($o['deliveryKop'] / 100, 2, '.', ''), '0'), '.')) ?>">
              </label>
              <button class="btn">Сохранить</button>
            </div>
          </form>
        <?php else: ?>
          <span><?= e($o['address']) ?></span>
          <span class="small muted">Зона <?= e($o['deliveryZone'] ?? '?') ?>, ~<?= e($o['distanceKm'] ?? '?') ?> км от магазина <?= e($shop['name'] ?? $o['shop']) ?></span>
        <?php endif ?>
        <span>Получатель: <?= e($o['recipientName'] ?: '—') ?> <?= $o['recipientPhone'] ? $tel($o['recipientPhone']) : '' ?></span>
        <?php if ($o['callRecipient']): ?><span class="small">Позвонить получателю перед доставкой</span><?php endif ?>
        <?php if ($o['anonymous']): ?><span class="small" style="font-weight: 800">Не говорить, от кого букет</span><?php endif ?>
      <?php else: ?>
        <span>Магазин: <?= e($shop ? "{$shop['name']}, {$shop['address']}" : $o['shop']) ?></span>
      <?php endif ?>
    </div>
    <div class="card stack" style="gap: 6px">
      <span class="label">Клиент</span>
      <span><?= e($o['customerName']) ?>, <?= $tel($o['customerPhone']) ?></span>
      <?php if ($o['comment']): ?><span class="muted">Комментарий: <?= e($o['comment']) ?></span><?php endif ?>
      <span class="small muted">Заказ оформлен <?= e($created) ?></span>
    </div>
    <?php if (!empty($o['company'])): ?>
      <div class="card stack" style="gap: 6px">
        <span class="label">Безнал: реквизиты для счёта</span>
        <div style="white-space: pre-line; overflow-wrap: anywhere"><?= e($o['company']['text'] ?? '') ?></div>
      </div>
    <?php endif ?>
  </div>
  <div class="card stack" style="gap: 10px">
    <div class="sum-row"><span>Товары</span><span><?= e(Money::format($o['itemsKop'])) ?></span></div>
    <?php if ($o['discountKop'] > 0): ?><div class="sum-row"><span>Скидка за самовывоз</span><span><?= e(Money::format(-$o['discountKop'])) ?></span></div><?php endif ?>
    <?php if ($o['deliveryMethod'] === 'DELIVERY'): ?>
      <div class="sum-row">
        <span><?= $o['addressUnknown'] ? 'Доплата за доставку' : 'Доставка' ?></span>
        <span><?= $o['addressUnknown'] && $o['deliveryKop'] === 0 ? 'ещё не назначена' : e(Money::format($o['deliveryKop'])) ?></span>
      </div>
    <?php endif ?>
    <div class="sum-row" style="align-items: baseline"><b>Итого</b><span class="sum-total"><?= admin_byn($o['totalKop']) ?></span></div>
    <div class="sum-row"><span>Оплата</span><span><?= e(ADMIN_PAY_LABEL[$o['paymentMethod']]) ?> · <?= $o['paid'] ? 'оплачен' : 'не оплачен' ?></span></div>
    <form method="post" action="/admin/orders/update" class="stack" style="gap: 8px">
      <?= Auth::csrfField() ?>
      <input type="hidden" name="id" value="<?= $o['id'] ?>">
      <label class="field">
        <span>Статус</span>
        <select class="input" name="status">
          <?php foreach (ADMIN_STATUS_LABEL as $k => $l): ?><option value="<?= $k ?>" <?= $o['status'] === $k ? 'selected' : '' ?>><?= e($l) ?></option><?php endforeach ?>
        </select>
      </label>
      <label class="field">
        <span>Оплата</span>
        <select class="input" name="paid">
          <option value="0">Не оплачен</option>
          <option value="1" <?= $o['paid'] ? 'selected' : '' ?>>Оплачен</option>
        </select>
      </label>
      <button class="btn">Сохранить</button>
    </form>
  </div>
</div>

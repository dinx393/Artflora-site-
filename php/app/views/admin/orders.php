<?php // Заказы = src/app/admin/(panel)/orders/page.tsx ?>
<h1>Заказы</h1>
<?php if ($unnotified > 0): ?>
  <p class="error">Уведомление в Telegram не дошло по <?= $unnotified ?> заказ(ам). Сайт повторяет отправку сам, но проверьте новые заказы здесь.</p>
<?php endif ?>
<?php if ($unemailed > 0): ?>
  <p class="error">Письмо о заказе не ушло на почту по <?= $unemailed ?> заказ(ам). Сайт повторяет отправку сам; если не проходит, проверьте настройки почты.</p>
<?php endif ?>
<div class="tabs">
  <a href="/admin/orders" class="chip <?= $active ? 'on' : '' ?>">В работе</a>
  <?php foreach (ADMIN_STATUS_LABEL as $k => $label): ?>
    <a href="/admin/orders?status=<?= $k ?>" class="chip <?= $status === $k ? 'on' : '' ?>"><?= e($label) ?> · <?= $counts[$k] ?? 0 ?></a>
  <?php endforeach ?>
  <a href="/admin/orders?status=all" class="chip <?= $status === 'all' ? 'on' : '' ?>">Все</a>
</div>
<?php if (!$orders): ?>
  <p class="muted">Заказов нет.</p>
<?php else: ?>
  <div class="table-wrap">
    <table class="table">
      <thead><tr><th>№</th><th>Когда</th><th>Что</th><th>Куда</th><th>Клиент</th><th>Сумма</th><th>Статус</th></tr></thead>
      <tbody>
        <?php foreach ($orders as $o): ?>
          <tr>
            <td><a href="/admin/orders/<?= $o['id'] ?>" style="font-weight: 800"><?= e($o['number']) ?></a></td>
            <td class="small"><?= e(admin_date($o['deliveryDate'])) ?>, <?= e($o['deliverySlot'] ?: 'время ?') ?></td>
            <td class="small"><?= e(implode(', ', array_map(fn ($i) => $i['name'] . ($i['size'] ? " {$i['size']}" : '') . ($i['qty'] > 1 ? " ×{$i['qty']}" : ''), $o['items']))) ?></td>
            <td class="small"><?= e($o['deliveryMethod'] === 'PICKUP' ? "Самовывоз · {$o['shop']}" : ($o['address'] ?: ($o['addressUnknown'] ? 'Уточнить у получателя' : ''))) ?></td>
            <td class="small"><?= e($o['customerName']) ?><br><?= e(Text::phone($o['customerPhone'])) ?></td>
            <td style="font-weight: 800"><?= e(Money::format($o['totalKop'])) ?><?php if ($o['paid']): ?><div class="pill ok">оплачен</div><?php endif ?></td>
            <td><span class="pill <?= $o['status'] === 'NEW' ? 'warn' : ($o['status'] === 'DONE' ? 'ok' : '') ?>"><?= e(ADMIN_STATUS_LABEL[$o['status']]) ?></span></td>
          </tr>
        <?php endforeach ?>
      </tbody>
    </table>
  </div>
<?php endif ?>

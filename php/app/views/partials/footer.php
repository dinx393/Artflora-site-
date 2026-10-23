<?php
// Подвал магазина. Переменные: $shop, $delivery
$tel = 'tel:+' . preg_replace('/\D/', '', $shop['phone']);
?>
<footer class="footer">
  <div class="wrap">
    <div class="footer-grid">
      <div class="stack" style="gap: 10px">
        <span class="footer-logo">Артфлора</span>
        <span class="soft">Цветочный магазин в Минске</span>
      </div>
      <div class="stack" style="gap: 6px">
        <?php foreach ($delivery['shops'] as $i => $s): ?>
          <div class="stack" style="gap: 4px; margin-top: <?= $i ? 10 : 0 ?>px">
            <b><?= e(mb_strtoupper(mb_substr($s['address'], 0, 1)) . mb_substr($s['address'], 1)) ?></b>
            <span class="soft"><?= e($s['name']) ?> · ежедневно <?= e($s['hours']) ?></span>
          </div>
        <?php endforeach ?>
      </div>
      <div class="stack" style="gap: 6px">
        <a href="<?= e($tel) ?>" class="footer-phone"><?= e($shop['phone']) ?></a>
        <div class="row" style="gap: 16px; margin-top: 6px">
          <a href="<?= e($shop['viber']) ?>">Viber</a>
          <a href="<?= e($shop['telegram']) ?>">Telegram</a>
          <a href="<?= e($shop['instagram']) ?>">Instagram</a>
        </div>
      </div>
      <div class="stack" style="gap: 6px">
        <b>Оплата</b>
        <span class="soft">Картой, через ЕРИП или ссылкой в Viber после подтверждения заказа</span>
        <a href="/delivery" class="soft">Доставка и оплата →</a>
      </div>
    </div>
    <div class="footer-legal">© Артфлора, <?= date('Y') ?> · <?= e($shop['sellerDetails']) ?></div>
  </div>
</footer>

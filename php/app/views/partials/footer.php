<?php
// Подвал магазина. Переменные: $shop, $delivery
$tel = 'tel:+' . preg_replace('/\D/', '', $shop['phone']);
$ny = Season::themeOn() ? Season::cfg() : null;
?>
<footer class="footer">
  <?php if ($ny): /* Новогоднее оформление: сугроб сверху, снег, заснеженные ёлки справа внизу */ ?>
    <svg class="ny-drift" viewBox="0 0 1280 34" preserveAspectRatio="none" aria-hidden="true"><path d="M0 0h1280v12c-58 14-104 18-162 8s-118-16-184-2-136 16-204 4-128-14-196 0-126 12-186 2S172 8 104 20 24 24 0 16z" fill="#fff"/></svg>
    <?php if (Season::snowOn()): ?><div class="ny-snow" aria-hidden="true"></div><?php endif ?>
    <?= Season::trees('ny-trees ny-trees-footer', '#6E1A35', true) ?>
  <?php endif ?>
  <div class="wrap">
    <div class="footer-grid">
      <div class="stack" style="gap: 10px">
        <span class="footer-logo">Artflora.by</span>
        <span class="soft">Цветочный магазин в Минске<?= $ny ? '. С наступающим!' : '' ?></span>
      </div>
      <div class="stack" style="gap: 6px">
        <?php foreach ($delivery['shops'] as $i => $s): ?>
          <div class="stack" style="gap: 4px; margin-top: <?= $i ? 10 : 0 ?>px">
            <b><?= ShopView::mapLink($s, mb_strtoupper(mb_substr($s['address'], 0, 1)) . mb_substr($s['address'], 1), 'map-link') ?></b>
            <span class="soft"><?= e($s['name']) ?> · <?= e($s['hours']) ?></span>
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
        <?php if ($ny && ($ny['hours31'] !== '' || $ny['hours1'] !== '')): ?>
          <span class="soft" style="margin-top: 10px"><?= e(implode(' · ', array_filter([$ny['hours31'] !== '' ? '31 декабря: ' . $ny['hours31'] : '', $ny['hours1'] !== '' ? '1 января: ' . $ny['hours1'] : '']))) ?></span>
        <?php endif ?>
      </div>
      <div class="stack" style="gap: 6px">
        <b>Оплата</b>
        <span class="soft">Через ЕРИП, картой Visa, MasterCard, МИР через WebPay, безнал для организаций</span>
        <a href="/delivery" class="soft">Доставка и оплата →</a>
        <a href="/delivery#vozvrat" class="soft">Обмен и возврат →</a>
        <a href="/blog" class="soft">Статьи о цветах →</a>
        <a href="/privacy" class="soft">Персональные данные и cookie →</a>
      </div>
    </div>
    <div class="footer-pay" aria-label="Принимаем к оплате">
      <img src="<?= View::asset('img/pay-1.png') ?>" alt="Visa, Visa Secure, Mastercard, Mastercard SecureCode" width="554" height="52" loading="lazy">
      <span class="pay-mir" aria-label="МИР">МИР</span>
      <img src="<?= View::asset('img/pay-2.png') ?>" alt="Белкарт, Белкарт ИнтернетПароль, bePaid, webpay, МТБанк" width="852" height="60" loading="lazy">
    </div>
    <div class="footer-legal">
      © Artflora.by, <?= date('Y') ?> · <?= e($shop['sellerDetails']) ?>
      <?php if ($shop['email']): ?> · E-mail: <a href="mailto:<?= e($shop['email']) ?>"><?= e($shop['email']) ?></a><?php endif ?>
      <?php if ($shop['complaints']): ?><br>Обращения покупателей рассматривает <?= e($shop['complaints']) ?><?php endif ?>
      <?php if ($shop['authority']): ?> · <?= e($shop['authority']) ?><?php endif ?>
    </div>
  </div>
</footer>

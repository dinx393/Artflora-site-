<?php
// Раздел «Предзаказ на неделю» (решение владельца 2026-11-01). Переменные: $list (букеты с галочкой «Предзаказ»), $info (Preorder::info), $shop.
// Цветы закупаем раз в неделю: успели до срока — закупим специально под заказ; позже — соберём из того, что есть (корзина предупредит).
?>
<section class="wrap catalog-head">
  <nav class="crumbs" aria-label="Навигация">
    <a href="/">Главная</a>
    <span aria-hidden="true">/</span>
    <a href="/catalog">Каталог</a>
    <span aria-hidden="true">/</span>
    <span>Предзаказ</span>
  </nav>
  <h1 class="h1">Предзаказ <span class="script">на неделю вперёд</span></h1>
</section>

<div class="wrap stack" style="gap: 28px; padding-bottom: 48px">
  <div class="panel stack preorder-rule" style="gap: 10px; max-width: 760px">
    <b style="font-size: 18px">Как работает предзаказ</b>
    <p style="margin: 0">Свежие цветы мы закупаем раз в неделю. Оформите предзаказ до <b><?= e($info['deadlineText']) ?></b>, и мы закупим цветы специально для вашего букета: доставим <b>с <?= e($info['fromText']) ?></b> и позже.</p>
    <p style="margin: 0">Цена цветов у поставщиков меняется каждую неделю, поэтому к вашей дате букет может стоить немного больше или меньше, чем сегодня. При оформлении выберите, что для вас важнее: <b>точная копия букета</b> (если цветы подорожают, доплата) или <b>сохранить бюджет</b> (флорист подберёт состав на ту же сумму).</p>
    <p class="small muted" style="margin: 0">Нужна дата раньше? Тоже можно: закупка на неё уже сделана, поэтому соберём букет из цветов, которые будут в магазине, и при необходимости согласуем с вами замену. Корзина предупредит об этом перед оформлением.</p>
  </div>

  <?php if ($list): ?>
    <div class="grid grid-3">
      <?php foreach ($list as $b) echo View::render('shop/_card', ['b' => $b, 'note' => 'Предзаказ: доставка с ' . $info['fromText']]) ?>
    </div>
  <?php else: ?>
    <div class="empty panel">
      <h2 class="h2">Букеты для предзаказа скоро появятся</h2>
      <p class="muted">А пока напишите флористу: соберём букет к нужной дате и закупим цветы под ваш заказ.</p>
      <div class="row" style="justify-content: center">
        <a href="<?= e($shop['telegram']) ?>" class="btn" target="_blank" rel="noopener">Написать флористу в Telegram</a>
        <a href="/catalog">Смотреть каталог →</a>
      </div>
    </div>
  <?php endif ?>
</div>

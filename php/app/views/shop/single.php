<?php // Цветы поштучно (src/app/(shop)/single/page.tsx). Переменные: $flowers, $shop ?>
<div class="section stack" style="gap: 24px">
  <h1 class="h2">Цветы <span class="script">поштучно</span></h1>
  <p class="muted" style="max-width: 640px">Соберём букет из любых цветов в наличии. Напишите флористу, сколько и каких цветов вы хотите, и пришлём фото до оплаты.</p>
  <div class="grid" style="grid-template-columns: repeat(auto-fill, minmax(220px, 1fr))">
    <?php foreach ($flowers as $f): ?>
      <div class="card stack" style="gap: 4px">
        <b><?= e($f['name']) ?></b>
        <span class="price" style="font-size: 24px"><?= ShopView::byn((int) $f['priceKop']) ?></span>
        <span class="small muted"><?= $f['unit'] === 'STEM' ? 'за ветку' : 'за штуку' ?></span>
      </div>
    <?php endforeach ?>
  </div>
  <a href="<?= e($shop['telegram']) ?>" class="btn btn-lg" style="align-self: flex-start" target="_blank" rel="noopener">Собрать букет с флористом в Telegram</a>
</div>

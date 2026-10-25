<?php
// Карточка букета в сетке (src/components/ProductCard.tsx). Переменные: $b, $note?
// Кнопку «В корзину» оживляет shop.js (data-add-to-cart).
$href = '/bouquet/' . rawurlencode($b['slug']);
?>
<div class="pcard">
  <a href="<?= e($href) ?>" class="pcard-photo">
    <img src="<?= e($b['photos'][0]) ?>" alt="<?= e($b['name']) ?>" loading="lazy">
    <?= ShopView::badges($b) ?>
  </a>
  <div class="pcard-body">
    <a href="<?= e($href) ?>" class="pcard-title" title="<?= e($b['name']) ?>"><?= e($b['name']) ?></a>
    <?php if (!empty($note)): ?><span class="small muted"><?= e($note) ?></span><?php endif ?>
    <div class="pcard-buy">
      <span class="price"><?= ShopView::byn($b['priceKop']) ?></span>
      <button type="button" class="btn btn-outline pcard-add" aria-label="Добавить в корзину" data-add-to-cart data-bouquet="<?= e($b['id']) ?>" data-size="<?= e($b['defaultSize']) ?>">
        <span data-icon><?= Icons::get('bag', 18) ?></span>
        <span class="pcard-add-word">В корзину</span>
      </button>
    </div>
  </div>
</div>

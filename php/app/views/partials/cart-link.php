<?php // Кнопка корзины: число товаров подставляет site.js из корзины в браузере ?>
<a href="/cart" class="btn" data-cart-link aria-label="Корзина">
  <?= Icons::get('bag') ?>
  <span><span class="cart-word">Корзина · </span><span data-cart-count>0</span></span>
</a>

<?php
// Статья: текст, справа «Нужен букет сегодня?», ниже букеты и другие статьи рубрики.
// Переменные: $a (Articles), $html (текст статьи), $related, $bouquets, $delivery
$rubric = Articles::RUBRICS[$a['rubric']];
?>
<div class="wrap article-wrap">
  <article class="article">
    <nav class="crumbs" aria-label="Навигация">
      <a href="/">Главная</a>
      <span aria-hidden="true">/</span>
      <a href="/blog">Статьи</a>
      <span aria-hidden="true">/</span>
      <a href="/blog#<?= e($a['rubric']) ?>"><?= e($rubric) ?></a>
    </nav>
    <h1><?= e($a['h1']) ?></h1>
    <span class="small muted">Флористы «Артфлора.by» · <time datetime="<?= e($a['date']) ?>"><?= e(Articles::date($a['date'])) ?></time></span>
    <div class="article-text"><?= $html ?></div>
  </article>
  <aside class="article-aside">
    <span class="article-aside-title">Нужен букет сегодня?</span>
    <span class="muted">Пришлём фото букета перед отправкой. Доставка по Минску от <?= e(Money::format(Delivery::minKop($delivery))) ?>, самовывоз −<?= e($delivery['pickupDiscountPct']) ?>%.</span>
    <a href="/catalog" class="btn btn-lg">Смотреть букеты</a>
  </aside>
</div>

<?php if ($bouquets): ?>
  <section class="section">
    <h2 class="h2 sec-title">Букеты <span class="script">для вас</span></h2>
    <div class="cards-4">
      <?php foreach ($bouquets as $b) echo View::render('shop/_card', ['b' => $b]) ?>
    </div>
  </section>
<?php endif ?>

<?php if ($related): ?>
  <section class="wrap articles-more">
    <b>Ещё статьи:</b>
    <?php foreach ($related as $r): ?><a href="/blog/<?= e($r['slug']) ?>"><?= e($r['h1']) ?></a><?php endforeach ?>
    <a href="/blog">все статьи</a>
  </section>
<?php endif ?>

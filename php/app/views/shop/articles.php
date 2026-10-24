<?php
// Страница «Статьи»: рубрики кнопками сверху, ниже статьи по рубрикам. Макет — доска «Ягодный сад», ряд «Статьи».
$groups = [];
foreach (Articles::RUBRICS as $code => $name) $groups[$code] = Articles::byRubric($code);
?>
<section class="wrap catalog-head">
  <nav class="crumbs" aria-label="Навигация">
    <a href="/">Главная</a>
    <span aria-hidden="true">/</span>
    <span>Статьи</span>
  </nav>
  <h1 class="h1">Статьи <span class="script">о цветах</span></h1>
  <div class="scroll-row">
    <div class="row chips-row" style="gap: 8px">
      <?php foreach (Articles::RUBRICS as $code => $name): ?>
        <a href="#<?= e($code) ?>" class="chip"><?= e($name) ?> · <?= count($groups[$code]) ?></a>
      <?php endforeach ?>
    </div>
  </div>
</section>

<?php foreach (Articles::RUBRICS as $code => $name): ?>
  <section class="wrap articles-group" id="<?= e($code) ?>">
    <h2 class="h2"><?= e($name) ?></h2>
    <div class="articles-grid">
      <?php foreach ($groups[$code] as $a): ?>
        <a href="/blog/<?= e($a['slug']) ?>" class="acard">
          <img src="<?= e($a['image'] ?? '/assets/img/og.jpg') ?>" alt="" loading="lazy">
          <span class="acard-title"><?= e($a['h1']) ?></span>
        </a>
      <?php endforeach ?>
    </div>
  </section>
<?php endforeach ?>

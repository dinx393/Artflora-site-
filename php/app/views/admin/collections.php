<?php // Коллекции = src/app/admin/(panel)/collections/page.tsx ?>
<div class="stack" style="gap: 4px">
  <h1>Коллекции</h1>
  <span class="muted">
    Коллекция объединяет один и тот же состав в разных видах: сумка, букет, шляпная коробка, корзина. На сайте это блок «Этот же состав в другом виде».
  </span>
</div>
<form method="post" action="/admin/collections/create" class="row" style="gap: 8px">
  <?= Auth::csrfField() ?>
  <input class="input" name="name" placeholder="Название новой коллекции, например rose" style="max-width: 360px" required>
  <button class="btn">+ Создать</button>
</form>
<?php foreach ($list as $c): ?>
  <div class="card stack" style="gap: 10px">
    <form method="post" action="/admin/collections/rename" class="row" style="gap: 8px">
      <?= Auth::csrfField() ?>
      <input type="hidden" name="id" value="<?= $c['id'] ?>">
      <input class="input" name="name" value="<?= e($c['name']) ?>" style="max-width: 300px; font-weight: 800">
      <button class="btn btn-outline">Переименовать</button>
    </form>
    <?php if (!$c['bouquets']): ?><span class="small muted">Пока пусто. Выберите эту коллекцию в карточке букета.</span><?php endif ?>
    <?php foreach ($c['bouquets'] as $b): ?>
      <div class="sum-row">
        <a href="/admin/bouquets/<?= $b['id'] ?>"><?= e($b['name']) ?></a>
        <span class="small muted"><?= e(Occasions::FORMATS[$b['format']]['label']) ?><?= $b['enabled'] ? '' : ' · выключен' ?></span>
      </div>
    <?php endforeach ?>
    <form method="post" action="/admin/collections/delete">
      <?= Auth::csrfField() ?>
      <input type="hidden" name="id" value="<?= $c['id'] ?>">
      <button class="btn-ghost small muted">Удалить коллекцию (букеты останутся)</button>
    </form>
  </div>
<?php endforeach ?>

<section class="section stack" style="gap: 16px; padding-bottom: 60px; text-align: center; align-items: center">
  <h1><?= e($title) ?></h1>
  <?php if ($message !== ''): ?><p class="muted"><?= e($message) ?></p><?php endif ?>
  <?php if ($code === 404): ?>
    <p class="muted">Возможно, букет уже убрали из каталога.</p>
    <a class="btn btn-lg" href="/catalog">Смотреть все букеты</a>
  <?php else: ?>
    <a class="btn btn-lg" href="/">На главную</a>
  <?php endif ?>
</section>

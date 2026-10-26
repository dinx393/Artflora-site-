<?php
// Оформление админки: меню слева. Переменные: $content, $title?
$items = [
    '/admin/bouquets' => ['Букеты', 'catalog'],
    '/admin/flowers' => ['Цветы', 'catalog'],
    '/admin/collections' => ['Коллекции', 'catalog'],
    '/admin/orders' => ['Заказы', 'orders'],
    '/admin/delivery' => ['Доставка', 'delivery'],
    '/admin/settings' => ['Настройки', 'settings'],
    '/admin/users' => ['Сотрудники', 'users'],
];
$items = array_map(fn ($x) => $x[0], array_filter($items, fn ($x) => Auth::can($x[1])));
$me = Auth::user();
$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
?>
<!doctype html>
<html lang="ru">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="color-scheme" content="light only">
<meta name="robots" content="noindex">
<meta name="csrf" content="<?= e(Auth::csrfToken()) ?>">
<title><?= e(($title ?? '') !== '' ? "$title — Управление сайтом" : 'Управление сайтом') ?></title>
<link rel="stylesheet" href="<?= View::asset('css/fonts.css') ?>">
<link rel="stylesheet" href="<?= View::asset('css/site.css') ?>">
<?= $head ?? '' ?>
</head>
<body>
<div class="admin">
  <aside class="admin-nav">
    <a href="/admin/bouquets" class="logo">Artflora.by</a>
    <span class="small muted" style="padding: 0 0 14px">Управление сайтом</span>
    <?php foreach ($items as $href => $label): ?>
      <a href="<?= $href ?>" class="item <?= str_starts_with($path, $href) ? 'on' : '' ?>"><?= e($label) ?></a>
    <?php endforeach ?>
    <a href="/" class="item small" style="margin-top: 16px; padding: 10px 12px; font-weight: 700" target="_blank">Открыть сайт →</a>
    <?php if ($me): ?><span class="small muted" style="margin-top: 12px; padding: 0 12px"><?= e($me['name']) ?> · <?= e(Auth::ROLES[$me['role']] ?? '') ?></span><?php endif ?>
    <form method="post" action="/admin/logout" style="display: flex; flex-direction: column; align-items: flex-start; gap: 6px">
      <?= Auth::csrfField() ?>
      <button class="btn-ghost small muted">Выйти</button>
      <button class="btn-ghost small muted" style="text-align: left" name="all" value="1" title="Например, если телефон потерялся">Выйти на всех устройствах</button>
    </form>
  </aside>
  <main class="admin-main"><?= $content ?></main>
</div>
<script src="<?= View::asset('js/admin.js') ?>"></script>
<?= $scripts ?? '' ?>
</body>
</html>

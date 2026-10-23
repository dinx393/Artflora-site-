<?php
// Оформление страниц магазина: вишнёвая полоска, шапка, подвал. Переменные: $content, $title?, $description?, $noindex?
$shop = Settings::get('shop');
$delivery = Settings::get('delivery');
$contacts = Text::contactLinks($shop);
$title = isset($title) && $title !== '' ? "$title — Артфлора" : 'Артфлора — цветы и букеты с доставкой по Минску';
$description ??= 'Сеть салонов цветов в Уручье и Малиновке, радуем букетами уже 19 лет. Пришлём фото букета до отправки, доставим по Минску в день заказа. Самовывоз −10%.';
?>
<!doctype html>
<html lang="ru">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="theme-color" content="#7E1F3C">
<title><?= e($title) ?></title>
<meta name="description" content="<?= e($description) ?>">
<?php if (!empty($noindex)): ?><meta name="robots" content="noindex"><?php endif ?>
<link rel="stylesheet" href="<?= View::asset('css/fonts.css') ?>">
<link rel="stylesheet" href="<?= View::asset('css/site.css') ?>">
<?= $head ?? '' ?>
</head>
<body>
<?= View::render('partials/header', ['shop' => $shop, 'delivery' => $delivery, 'contacts' => $contacts]) ?>
<main><?= $content ?></main>
<?= View::render('partials/footer', ['shop' => $shop, 'delivery' => $delivery]) ?>
<script src="<?= View::asset('js/site.js') ?>"></script>
<?= $scripts ?? '' ?>
</body>
</html>

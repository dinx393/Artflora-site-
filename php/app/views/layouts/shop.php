<?php
// Оформление страниц магазина: вишнёвая полоска, шапка, подвал.
// Переменные: $content, $title?, $description?, $noindex?, $canonical? (путь; по умолчанию — адрес без ?…),
// $ogImage?, $ogType?, $jsonLd? (список массивов schema.org, см. Seo)
$shop = Settings::get('shop');
$delivery = Settings::get('delivery');
$contacts = Text::contactLinks($shop);
$title = isset($title) && $title !== '' ? (str_contains($title, 'Артфлор') ? $title : "$title — Артфлора") : 'Доставка цветов в Минске: букеты с доставкой на дом — Артфлора';
$description ??= 'Цветы и букеты с доставкой по Минску в день заказа. Салоны в Уручье и Малиновке, радуем букетами уже 19 лет. Пришлём фото букета до отправки, самовывоз −10%.';
// Копии сайта (test.artflora.by) поисковикам не показываем
$noindex = !empty($noindex) || !Seo::isMainSite();
$canonical = array_key_exists('canonical', get_defined_vars()) ? $canonical : strtok($_SERVER['REQUEST_URI'] ?? '/', '?');
$canonicalUrl = $canonical !== null ? Seo::absUrl($canonical) : null;
$ogImageUrl = Seo::absUrl($ogImage ?? '/assets/img/og.jpg');
?>
<!doctype html>
<html lang="ru">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="theme-color" content="#7E1F3C">
<title><?= e($title) ?></title>
<meta name="description" content="<?= e($description) ?>">
<?php if ($noindex): ?><meta name="robots" content="noindex"><?php endif ?>
<?php if ($canonicalUrl && !$noindex): ?><link rel="canonical" href="<?= e($canonicalUrl) ?>"><?php endif ?>
<meta property="og:type" content="<?= e($ogType ?? 'website') ?>">
<meta property="og:site_name" content="Артфлора">
<meta property="og:locale" content="ru_RU">
<meta property="og:title" content="<?= e($title) ?>">
<meta property="og:description" content="<?= e($description) ?>">
<?php if ($canonicalUrl): ?><meta property="og:url" content="<?= e($canonicalUrl) ?>"><?php endif ?>
<meta property="og:image" content="<?= e($ogImageUrl) ?>">
<meta name="twitter:card" content="summary_large_image">
<link rel="icon" href="/favicon.ico?v=2" sizes="any">
<link rel="icon" type="image/png" sizes="192x192" href="<?= View::asset('img/icon-192.png') ?>">
<link rel="apple-touch-icon" href="<?= View::asset('img/apple-touch-icon.png') ?>">
<?php foreach ($jsonLd ?? [] as $ld) echo Seo::jsonLd($ld) ?>
<link rel="stylesheet" href="<?= View::asset('css/fonts.css') ?>">
<link rel="stylesheet" href="<?= View::asset('css/site.css') ?>">
<?= $head ?? '' ?>
<?= View::render('partials/counters', ['part' => 'head']) ?>
</head>
<body>
<?= View::render('partials/header', ['shop' => $shop, 'delivery' => $delivery, 'contacts' => $contacts]) ?>
<main><?= $content ?></main>
<?= View::render('partials/footer', ['shop' => $shop, 'delivery' => $delivery]) ?>
<script src="<?= View::asset('js/site.js') ?>"></script>
<?= $scripts ?? '' ?>
<?= View::render('partials/counters', ['part' => 'body']) ?>
</body>
</html>

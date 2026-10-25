<!doctype html>
<html lang="ru">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="color-scheme" content="light only">
<meta name="robots" content="noindex">
<title><?= e(($title ?? '') !== '' ? "$title — Artflora.by" : 'Artflora.by') ?></title>
<link rel="stylesheet" href="<?= View::asset('css/fonts.css') ?>">
<link rel="stylesheet" href="<?= View::asset('css/site.css') ?>">
</head>
<body><?= $content ?></body>
</html>

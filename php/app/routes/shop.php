<?php
// Витрина: главная, каталог, страница букета, поштучно, доставка и оплата, напоминания.
// Повторяет src/app/(shop)/page.tsx, catalog, bouquet/[slug], single, delivery, remind.

Http::get('/', function () {
    $all = Catalog::visible();
    $shop = Settings::get('shop');
    $price = Http::str('price', 20);
    $range = array_values(array_filter(Occasions::PRICE_RANGES, fn ($r) => $r['slug'] === $price))[0] ?? null;
    $hitsOnly = array_values(array_filter($all, fn ($b) => $b['isHit']));
    $hitsAll = count($hitsOnly) >= 4 ? $hitsOnly : $all;
    $hits = $range ? array_filter($hitsAll, fn ($b) => $b['priceKop'] >= $range['min'] && $b['priceKop'] < $range['max']) : $hitsAll;
    // «Букет дня» выбирают в админке → «Настройки»; если не выбран или скрыт — первый хит.
    $find = fn (callable $fn) => array_values(array_filter($all, $fn))[0] ?? null;
    $ofDay = $find(fn ($b) => $b['slug'] === $shop['dayBouquet']) ?? $find(fn ($b) => $b['isHit']) ?? ($all[0] ?? null);
    // Плитка «Весь каталог» — три разных букета рядом: вертикальные фото так обрезаются меньше, чем одно на всю ширину.
    $tile = [];
    foreach ($all as $b) {
        $p = $b['photos'][0] ?? null;
        if ($ofDay && $b['id'] === $ofDay['id']) continue;
        if (!$p || ($ofDay && $p === $ofDay['photos'][0]) || in_array($p, $tile, true)) continue;
        $tile[] = $p;
        if (count($tile) === 3) break;
    }
    if (!$tile) $tile[] = '/demo/autumn.jpg'; // каталог пока пуст — фото букета из магазина
    return View::page('shop/home', [
        'all' => $all,
        'shop' => $shop,
        'delivery' => Settings::get('delivery'),
        'price' => $price,
        'range' => $range,
        'hits' => array_slice(array_values($hits), 0, 8),
        'ofDay' => $ofDay,
        'tilePhotos' => $tile,
        'exclusive' => array_slice(array_values(array_filter($all, fn ($b) => $b['isExclusive'])), 0, 3),
        'scripts' => '<script src="' . View::asset('js/shop.js') . '"></script>',
    ]);
});

Http::get('/catalog', function () {
    $keys = ['occasion', 'price', 'flower', 'color', 'format', 'collection', 'q', 'sort', 'n'];
    $sp = [];
    foreach ($keys as $k) if (($v = Http::str($k, 100)) !== '') $sp[$k] = $v;
    $all = Catalog::visible();
    $colorList = Settings::get('colors');
    $range = array_values(array_filter(Occasions::PRICE_RANGES, fn ($r) => $r['slug'] === ($sp['price'] ?? '')))[0] ?? null;
    $colors = Occasions::parseColors($sp['color'] ?? null, $colorList);
    $list = Catalog::filter($all, [
        'occasion' => $sp['occasion'] ?? null,
        'price' => $range ? ['min' => $range['min'], 'max' => $range['max']] : null,
        'flower' => $sp['flower'] ?? null,
        'colors' => $colors,
        'format' => $sp['format'] ?? null,
        'collection' => $sp['collection'] ?? null,
        'q' => $sp['q'] ?? null,
    ]);
    $sort = $sp['sort'] ?? '';
    if ($sort === 'cheap') usort($list, fn ($a, $b) => $a['priceKop'] <=> $b['priceKop']);
    if ($sort === 'expensive') usort($list, fn ($a, $b) => $b['priceKop'] <=> $a['priceKop']);
    $occ = array_values(array_filter(Occasions::LIST, fn ($o) => $o['slug'] === ($sp['occasion'] ?? '')))[0] ?? null;
    return View::page('shop/catalog', [
        'title' => $occ ? "Букеты {$occ['title']}" : 'Каталог букетов',
        'sp' => $sp,
        'all' => $all,
        'list' => $list,
        'range' => $range,
        'occ' => $occ,
        'colors' => $colors,
        'colorList' => $colorList,
        // В фильтре только цвета, которые есть хотя бы у одного букета (и уже выбранные).
        'palette' => array_values(array_filter($colorList, fn ($c) => in_array($c['slug'], $colors, true)
            || array_filter($all, fn ($b) => in_array($c['slug'], $b['colors'], true)))),
        'flowers' => Db::all("SELECT id, name FROM flowers WHERE kind = 'FLOWER' ORDER BY sortOrder, name"),
        'addons' => ShopView::addons(),
        'shop' => Settings::get('shop'),
        'delivery' => Settings::get('delivery'),
        'scripts' => '<script src="' . View::asset('js/shop.js') . '"></script>',
    ]);
});

Http::get('/bouquet/{slug}', function (string $slug) {
    $b = Catalog::bySlug($slug) ?? Http::abort(404);
    $all = Catalog::visible();
    $siblings = $b['collectionId'] ? array_values(array_filter($all, fn ($x) => $x['collectionId'] === $b['collectionId'])) : [];
    $similar = [];
    foreach ($all as $x) {
        if ($x['id'] === $b['id'] || ($b['collectionId'] && $x['collectionId'] === $b['collectionId'])) continue;
        $score = count(array_intersect($x['occasions'], $b['occasions'])) * 2 - abs($x['priceKop'] - $b['priceKop']) / 5000;
        $similar[] = [$x, $score];
    }
    usort($similar, fn ($p, $q) => $q[1] <=> $p[1]);
    $def = array_values(array_filter($b['sizes'], fn ($s) => $s['code'] === $b['defaultSize']))[0];
    $delivery = Settings::get('delivery');
    return View::page('shop/bouquet', [
        'title' => $b['name'] . ' — ' . Money::format($b['priceKop']),
        'description' => $b['description'] ?: Occasions::FORMATS[$b['format']]['label'] . ': ' . $def['composition'],
        'head' => '<meta property="og:image" content="' . e($b['photos'][0]) . '">',
        'b' => $b,
        'def' => $def,
        'siblings' => $siblings,
        'similar' => array_map(fn ($p) => $p[0], array_slice($similar, 0, 4)),
        'shop' => Settings::get('shop'),
        'delivery' => $delivery,
        'districts' => Delivery::districtOptions(Settings::get('districts'), $delivery, $def['priceKop']),
        'addons' => ShopView::addons(false),
        'scripts' => '<script src="' . View::asset('js/shop.js') . '"></script>',
    ]);
});

Http::get('/single', fn () => View::page('shop/single', [
    'title' => 'Цветы поштучно',
    'flowers' => Db::all("SELECT id, name, unit, priceKop FROM flowers WHERE inStock = 1 ORDER BY kind = 'GREEN', sortOrder, name"),
    'shop' => Settings::get('shop'),
]));

Http::get('/delivery', fn () => View::page('shop/delivery', [
    'title' => 'Доставка и оплата',
    'shop' => Settings::get('shop'),
    'd' => Settings::get('delivery'),
]));

Http::get('/remind', fn () => View::page('shop/remind', [
    'title' => 'Напомним о важных датах',
    'ok' => isset($_GET['ok']),
    'error' => Http::str('error', 10),
]));

Http::post('/remind', function () {
    if (!RateLimit::allow('remind', RateLimit::clientIp())) Http::redirect('/remind?error=busy');
    $name = Http::str('name', 1000);
    $phone = preg_replace('/[^\d+]/', '', Http::str('phone', 1000));
    $occasion = Http::str('occasion', 1000);
    $date = Http::str('date', 20);
    $d = DateTime::createFromFormat('!Y-m-d', $date);
    $valid = $name !== '' && mb_strlen($name) <= 100 && preg_match('/^\+?\d{9,13}$/', $phone)
        && $occasion !== '' && mb_strlen($occasion) <= 100 && $d && $d->format('Y-m-d') === $date;
    if (!$valid) Http::redirect('/remind?error=1');
    Db::insert('reminders', ['name' => $name, 'phone' => $phone, 'occasion' => $occasion, 'date' => $date]);
    Http::redirect('/remind?ok=1');
});

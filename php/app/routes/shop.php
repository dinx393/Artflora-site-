<?php
// Витрина: главная, каталог, страница букета, поштучно, доставка и оплата, напоминания.
// Повторяет src/app/(shop)/page.tsx, catalog, bouquet/[slug], single, delivery.

Http::get('/', function () {
    $all = Catalog::general(Catalog::visible());
    $shop = Settings::get('shop');
    $price = Http::str('price', 20);
    $range = array_values(array_filter(Occasions::PRICE_RANGES, fn ($r) => $r['slug'] === $price))[0] ?? null;
    $hitsOnly = array_values(array_filter($all, fn ($b) => $b['isHit']));
    $hitsAll = count($hitsOnly) >= 4 ? $hitsOnly : $all;
    $hits = $range ? array_filter($hitsAll, fn ($b) => $b['priceKop'] >= $range['min'] && $b['priceKop'] < $range['max']) : $hitsAll;
    // «Букет дня» — галочка в карточке букета (или «Настройки»); если не выбран или скрыт — первый букет каталога.
    $find = fn (callable $fn) => array_values(array_filter($all, $fn))[0] ?? null;
    // Скрылся по таймеру — показываем самый свежий букет, у которого ещё идёт таймер «собран сегодня».
    $fresh = array_filter($all, fn ($b) => $b['hideAt'] !== null);
    usort($fresh, fn ($a, $b) => strcmp($b['hideAt'], $a['hideAt']));
    $ofDay = $find(fn ($b) => $b['slug'] === $shop['dayBouquet']) ?? ($fresh[0] ?? null) ?? ($all[0] ?? null);
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
        'jsonLd' => [Seo::business($shop, Settings::get('delivery'))],
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

// Каталог. $landing — раздел с понятным адресом (/catalog/svadebnye-bukety, Seo::LANDINGS): его фильтр уже выбран.
function catalog_page(?string $landingSlug = null): string
{
    $landing = $landingSlug !== null ? Seo::LANDINGS[$landingSlug] : null;
    $keys = ['occasion', 'price', 'flower', 'color', 'format', 'collection', 'q', 'sort', 'n'];
    $sp = $landing['filter'] ?? [];
    foreach ($keys as $k) if (($v = Http::str($k, 100)) !== '') $sp[$k] = $v;
    $all = Catalog::visible();
    // Свадебные «только в разделе» — лишь в «Свадебных букетах» (Catalog::filter убирает их и сам; здесь — для фильтра цветов)
    if (($sp['occasion'] ?? '') !== 'wedding') $all = Catalog::general($all);
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
    $canonical = Seo::catalogCanonical($sp);
    $isLanding = $landing && $canonical === "/catalog/$landingSlug";
    return View::page('shop/catalog', [
        'title' => $isLanding ? $landing['title'] : ($occ ? "Букеты {$occ['title']} с доставкой по Минску" : 'Каталог букетов с доставкой по Минску'),
        'description' => $isLanding ? $landing['description']
            : 'Букеты, композиции в коробках и корзинах с доставкой по Минску в день заказа. Фото букета до отправки, самовывоз −10%.',
        'canonical' => $canonical,
        'noindex' => $canonical === null,
        'landing' => $isLanding ? $landing : null,
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
        // В фильтре «Цветок» только основные цветы (галочка «В фильтре» в админке), дополняющие не показываем
        'flowers' => Db::all("SELECT id, name FROM flowers WHERE kind = 'FLOWER' AND inFilter = 1 ORDER BY sortOrder, name"),
        'addons' => ShopView::addons(),
        'shop' => Settings::get('shop'),
        'delivery' => Settings::get('delivery'),
        'scripts' => '<script src="' . View::asset('js/shop.js') . '"></script>',
    ]);
}

Http::get('/catalog', fn () => catalog_page());

Http::get('/bouquet/{slug}', function (string $slug) {
    $b = Catalog::bySlug($slug);
    // Букет есть, но сейчас скрыт (нет цветов): временно ведём в каталог, чтобы поиск не терял страницу
    if (!$b) Catalog::load('b.slug = ?', [$slug]) ? Http::redirect('/catalog', 302) : Http::abort(404);
    $all = Catalog::visible();
    // «Похожие» к обычному букету — без свадебных, которые только в своём разделе
    if (!$b['weddingOnly']) $all = Catalog::general($all);
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
        'title' => $b['name'] . ' — купить в Минске за ' . Money::format($b['priceKop']),
        'description' => $b['description'] ?: Occasions::FORMATS[$b['format']]['label'] . ': ' . $def['composition'],
        'ogImage' => $b['photos'][0],
        'ogType' => 'product',
        'jsonLd' => [
            Seo::product($b, Seo::absUrl("/bouquet/{$b['slug']}")),
            Seo::breadcrumbs([['Главная', '/'], ['Каталог', '/catalog'], [$b['name'], null]]),
        ],
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
    'title' => 'Цветы поштучно в Минске',
    'description' => 'Розы, хризантемы, эустома и другие цветы поштучно: соберём букет из выбранных цветов и доставим по Минску. Цены за штуку на сайте.',
    'flowers' => Db::all("SELECT id, name, unit, priceKop FROM flowers WHERE inStock = 1 ORDER BY kind = 'GREEN', sortOrder, name"),
    'shop' => Settings::get('shop'),
]));

Http::get('/privacy', fn () => View::page('shop/privacy', [
    'title' => 'Политика обработки персональных данных',
    'description' => 'Как «Artflora.by» обрабатывает и защищает данные покупателей и получателей букетов, cookie и счётчики на сайте.',
    'shop' => Settings::get('shop'),
]));

Http::get('/delivery', fn () => View::page('shop/delivery', [
    'title' => 'Доставка цветов по Минску и оплата',
    'description' => 'Сколько стоит доставка цветов по Минску и за МКАД, бесплатная доставка, самовывоз −10%, оплата через ЕРИП, картой Visa, MasterCard, МИР через WebPay или безналичным расчётом для организаций.',
    'shop' => Settings::get('shop'),
    'd' => Settings::get('delivery'),
    'scripts' => ShopView::zonesMapScripts(),
]));

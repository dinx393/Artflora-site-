<?php
group('поиск: адреса старого сайта');
// Все адреса из sitemap.xml старого artflora.by (2026-10-24) ведут на живую страницу нового сайта
test('каждый старый адрес знает, куда вести', function () {
    $pages = ['/', '/blog', '/catalog', '/delivery', '/single'];
    foreach (file(__DIR__ . '/old-urls.txt', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $path) {
        if (in_array($path, $pages, true)) continue;
        if (preg_match('#^/blog/(.+)$#', $path, $m)) {
            ok(Articles::bySlug($m[1]) || isset(Articles::REMOVED[$m[1]]), "статья $path");
        } elseif (preg_match('#^/catalog/(.+)$#', $path, $m)) {
            $to = Seo::oldCatalogTarget($m[1]);
            ok($to === '/catalog' || isset(Seo::LANDINGS[substr($to, 9)]), "раздел $path → $to");
        } elseif (preg_match('#^/products/(.+)$#', $path, $m)) {
            $to = Seo::oldProductTarget($m[1]);
            ok($to === '/catalog' || isset(Seo::LANDINGS[substr($to, 9)]), "букет $path → $to");
        } else {
            ok(isset(Seo::OLD_PAGES[$path]), "страница $path");
        }
    }
});
test('похожие разделы указывают на существующие', function () {
    foreach (Seo::OLD_CATALOG as $from => $to) ok(isset(Seo::LANDINGS[$to]), "$from → $to");
    foreach (Seo::PRODUCT_WORDS as $w => $to) ok(isset(Seo::LANDINGS[$to]), "$w → $to");
});
test('свадебный букет из роз — в свадебные', fn () => eq(Seo::oldProductTarget('svadebnyj-buket-iz-roz-i-eustomy'), '/catalog/svadebnye-bukety'));
test('шляпная коробка', fn () => eq(Seo::oldProductTarget('shlyapnaya-korobka-rozovyj-shik'), '/catalog/kompozitsii-v-shlyapnyh-korobkah'));
test('неизвестный раздел — в каталог', fn () => eq(Seo::oldCatalogTarget('podsolnuhi'), '/catalog'));

group('поиск: canonical каталога');
test('повод → адрес раздела', fn () => eq(Seo::catalogCanonical(['occasion' => 'wedding', 'sort' => 'cheap']), '/catalog/svadebnye-bukety'));
test('без фильтра — каталог', fn () => eq(Seo::catalogCanonical(['color' => 'red']), '/catalog'));
test('поиск не индексируем', fn () => eq(Seo::catalogCanonical(['q' => 'роза']), null));
test('два фильтра сразу не индексируем', fn () => eq(Seo::catalogCanonical(['occasion' => 'mom', 'format' => 'BAG']), null));
test('фильтр без раздела — обычный адрес', fn () => eq(Seo::catalogUrl(['flower' => 'Эустома']), '/catalog?flower=%D0%AD%D1%83%D1%81%D1%82%D0%BE%D0%BC%D0%B0'));

group('поиск: разметка');
test('часы работы', function () {
    eq(Seo::hours('8:00–21:00'), ['08:00', '21:00']);
    $o = Seo::openingHours('пн–сб 8:00–21:00, вс 10:00–21:00');
    eq(count($o), 2);
    eq($o[0]['dayOfWeek'], ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday']);
    eq([$o[0]['opens'], $o[0]['closes']], ['08:00', '21:00']);
    eq([$o[1]['dayOfWeek'], $o[1]['opens']], [['Sunday'], '10:00']);
    eq(count(Seo::openingHours('8:00–21:00')[0]['dayOfWeek']), 7);
});
test('json без </script> внутри', fn () => ok(!str_contains(Seo::jsonLd(['x' => '</script>']), '</script><')));

group('статьи');
test('у каждой статьи есть текст и рубрика', function () {
    foreach (Articles::all() as $a) {
        ok(is_file(APP_DIR . "/content/articles/{$a['slug']}.html"), "текст {$a['slug']}");
        ok(isset(Articles::RUBRICS[$a['rubric']]), "рубрика {$a['slug']}");
        ok(!preg_match('/20[12]\d/', $a['h1'] . $a['title']), "год в заголовке {$a['slug']}");
    }
});
test('в текстах нет устаревшего', function () {
    foreach (glob(APP_DIR . '/content/articles/*.html') as $f) {
        $s = file_get_contents($f);
        foreach (['653-35-58', 'коронавирус', 'короновирус', 'бесконтактн', 'Грушев', 'от 50 рублей', '15 лет'] as $bad) {
            ok(!str_contains($s, $bad), basename($f) . ": «{$bad}»");
        }
    }
});

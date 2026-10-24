<?php
// Для поисковиков: robots.txt, sitemap.xml, разделы каталога с понятными адресами
// и постоянные (301) перенаправления со старого сайта artflora.by. Таблицы адресов — в app/lib/Seo.php.

Http::get('/robots.txt', function () {
    header('Content-Type: text/plain; charset=utf-8');
    // Копии сайта (test.artflora.by) закрыты целиком, чтобы поиск не путал их с основным
    if (!Seo::isMainSite()) return "User-agent: *\nDisallow: /\n";
    return "User-agent: *\n"
        . "Disallow: /admin\nDisallow: /api/\nDisallow: /cart\nDisallow: /order/\n"
        . "Disallow: /catalog?q=\nDisallow: /*?*sort=\nDisallow: /*?*n=\n"
        . "Clean-param: utm_source&utm_medium&utm_campaign&utm_content&utm_term&yclid&gclid&fbclid\n"
        . 'Sitemap: ' . Seo::absUrl('/sitemap.xml') . "\n";
});

Http::get('/sitemap.xml', function () {
    $all = Catalog::visible();
    $urls = [['/', 'daily', '1.0'], ['/catalog', 'daily', '0.9']];
    // Разделы каталога — только непустые, пустой раздел поисковику не нужен
    foreach (Seo::LANDINGS as $slug => $l) {
        $f = $l['filter'];
        $range = isset($f['price']) ? array_values(array_filter(Occasions::PRICE_RANGES, fn ($r) => $r['slug'] === $f['price']))[0] : null;
        $list = Catalog::filter($all, ['price' => $range ? ['min' => $range['min'], 'max' => $range['max']] : null] + $f);
        if ($list) $urls[] = ["/catalog/$slug", 'daily', '0.8'];
    }
    foreach ($all as $b) $urls[] = ["/bouquet/{$b['slug']}", 'weekly', '0.7'];
    $urls[] = ['/single', 'weekly', '0.6'];
    $urls[] = ['/delivery', 'monthly', '0.6'];
    $urls[] = ['/blog', 'monthly', '0.5'];
    foreach (Articles::all() as $a) $urls[] = ["/blog/{$a['slug']}", 'yearly', '0.4'];

    header('Content-Type: application/xml; charset=utf-8');
    $out = '<?xml version="1.0" encoding="UTF-8"?>' . "\n" . '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
    foreach ($urls as [$path, $freq, $prio]) {
        $out .= '  <url><loc>' . e(Seo::absUrl($path)) . "</loc><changefreq>$freq</changefreq><priority>$prio</priority></url>\n";
    }
    return $out . "</urlset>\n";
});

// Разделы: /catalog/svadebnye-bukety и т. п. Старые разделы без пары — 301 на похожий раздел или в каталог.
Http::get('/catalog/{slug}', function (string $slug) {
    if (isset(Seo::LANDINGS[$slug])) return catalog_page($slug);
    Http::redirect(Seo::oldCatalogTarget($slug), 301);
});

// Карточки букетов старого сайта: букет с тем же адресом есть на новом сайте — на него, иначе в похожий раздел
Http::get('/products/{slug}', function (string $slug) {
    if (Catalog::load('b.slug = ?', [$slug])) Http::redirect('/bouquet/' . rawurlencode($slug), 301);
    Http::redirect(Seo::oldProductTarget($slug), 301);
});

foreach (Seo::OLD_PAGES as $from => $to) Http::get($from, fn () => Http::redirect($to, 301));

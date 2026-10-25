<?php
// Статьи: /blog — список по рубрикам, /blog/<slug> — статья. В меню их нет, ссылка — в подвале (решение владельца 2026-10-24).

Http::get('/blog', fn () => View::page('shop/articles', [
    'title' => 'Статьи о цветах: советы флориста',
    'description' => 'Советы флористов «Artflora.by»: как выбрать букет, какие цветы подарить маме, на свадьбу или день рождения, как продлить жизнь цветам.',
    'jsonLd' => [Seo::breadcrumbs([['Главная', '/'], ['Статьи', null]])],
]));

Http::get('/blog/{slug}', function (string $slug) {
    if (isset(Articles::REMOVED[$slug])) Http::redirect(Articles::REMOVED[$slug], 301);
    $a = Articles::bySlug($slug) ?? Http::abort(404);
    $url = Seo::absUrl("/blog/$slug");
    // Под статьёй — букеты: к свадебным статьям свадебные, к остальным хиты
    $all = Catalog::visible();
    $pick = $a['rubric'] === 'svadba' ? Catalog::filter($all, ['occasion' => 'wedding']) : array_filter($all, fn ($b) => $b['isHit']);
    $bouquets = array_slice(array_values($pick ?: $all), 0, 4);
    return View::page('shop/article', [
        'title' => $a['title'],
        'description' => Articles::fill($a['description']),
        'ogType' => 'article',
        'ogImage' => $a['image'] ?? null,
        'jsonLd' => [
            array_filter([
                '@context' => 'https://schema.org', '@type' => 'Article',
                'headline' => $a['h1'], 'description' => Articles::fill($a['description']),
                'image' => $a['image'] ? [Seo::absUrl($a['image'])] : null,
                'datePublished' => $a['date'], 'dateModified' => '2026-10-24',
                'author' => ['@type' => 'Organization', 'name' => 'Флористы «Artflora.by»', 'url' => Seo::absUrl('/')],
                'publisher' => ['@id' => Config::siteUrl() . '/#org'],
                'mainEntityOfPage' => $url, 'inLanguage' => 'ru',
            ]),
            Seo::breadcrumbs([['Главная', '/'], ['Статьи', '/blog'], [$a['h1'], null]]),
        ],
        'a' => $a,
        'html' => Articles::html($a),
        'related' => Articles::related($a),
        'bouquets' => $bouquets,
        'delivery' => Settings::get('delivery'),
        'scripts' => '<script src="' . View::asset('js/shop.js') . '"></script>',
    ]);
});

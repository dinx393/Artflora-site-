<?php
group('таймер «собран сегодня»');
test('сколько осталось — коротко', function () {
    $now = strtotime('2026-10-28 12:00:00');
    eq(Catalog::timerLeft('2026-10-30 00:00:00', $now), '1 д 12 ч');
    eq(Catalog::timerLeft('2026-10-29 12:00:00', $now), '1 д');
    eq(Catalog::timerLeft('2026-10-28 17:20:00', $now), '5 ч 20 мин');
    eq(Catalog::timerLeft('2026-10-28 12:00:30', $now), '1 мин');
    eq(Catalog::timerLeft('2026-10-28 11:00:00', $now), '1 мин', 'срок уже прошёл');
    eq(Catalog::timerDate('2026-10-29 09:05:00'), '29.10 в 09:05');
});

group('свадебные только в своём разделе');
test('видны только в «Свадебных букетах»', function () {
    $w = ['name' => 'Невеста', 'description' => '', 'flowerNames' => ['Роза'], 'occasions' => ['wedding', 'love'], 'colors' => [], 'sections' => [],
        'format' => 'BOUQUET', 'collectionSlug' => null, 'priceKop' => 10000, 'weddingOnly' => true];
    $any = ['weddingOnly' => false, 'name' => 'Обычный'] + $w;
    $names = fn ($l) => array_column($l, 'name');
    eq($names(Catalog::filter([$w, $any], [])), ['Обычный'], 'общий каталог');
    eq($names(Catalog::filter([$w, $any], ['q' => 'роза'])), ['Обычный'], 'поиск');
    eq($names(Catalog::filter([$w, $any], ['occasion' => 'love'])), ['Обычный'], 'другой повод');
    eq($names(Catalog::filter([$w, $any], ['occasion' => 'wedding'])), ['Невеста', 'Обычный'], 'свадебный раздел');
    eq($names(Catalog::general([$w, $any])), ['Обычный'], 'главная');
});

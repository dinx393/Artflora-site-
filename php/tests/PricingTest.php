<?php
group('цена букета');
$rose = ['id' => 1, 'name' => 'Роза кустовая 60 см', 'priceKop' => 1000, 'inStock' => true];
$eustoma = ['id' => 2, 'name' => 'Эустома', 'priceKop' => 1300, 'inStock' => true];
$eucalyptus = ['id' => 3, 'name' => 'Эвкалипт', 'priceKop' => 500, 'inStock' => true];
$peony = ['id' => 4, 'name' => 'Пион', 'priceKop' => 2000, 'inStock' => false];
$bag = ['enabled' => true, 'sizes' => [
    ['code' => 'M', 'note' => '', 'packagingKop' => 1500, 'items' => [['qty' => 5, 'flower' => $rose], ['qty' => 3, 'flower' => $eustoma], ['qty' => 2, 'flower' => $eucalyptus]]],
    ['code' => 'L', 'note' => '', 'packagingKop' => 2500, 'items' => [['qty' => 7, 'flower' => $rose], ['qty' => 1, 'flower' => $peony]]],
]];

test('Σ цветы × количество + упаковка (сумка «rose» из макета = 114 BYN)', fn () => eq(Pricing::rawSizeKop($bag['sizes'][0]), 11400));
test('на сайте цена вверх до 5 BYN: 114 → 115, 177 → 180, 93 → 95, 120 → 120', function () use ($bag) {
    eq(Pricing::sizePriceKop($bag['sizes'][0]), 11500);
    eq(array_map([Pricing::class, 'roundUpKop'], [17700, 9300, 12000, 12001, 19700, 0]), [18000, 9500, 12000, 12500, 20000, 0]);
});
test('у каждого размера своя упаковка', function () use ($rose, $bag) {
    eq(Pricing::sizePriceKop(['items' => [['qty' => 1, 'flower' => $rose]], 'packagingKop' => 500]), 1500);
    eq(Pricing::sizePriceKop($bag['sizes'][1]), 7 * 1000 + 2000 + 2500);
});
test('размер с отсутствующим цветком не предлагается', function () use ($bag) {
    eq(Pricing::defaultSize($bag)['code'], 'M');
    eq(Pricing::visibility($bag), ['visible' => true]);
});
test('по умолчанию — размер «на фото»; если его не собрать — M', function () use ($bag, $rose) {
    $small = $bag;
    array_unshift($small['sizes'], ['code' => 'S', 'note' => '', 'packagingKop' => 1000, 'items' => [['qty' => 3, 'flower' => $rose]]]);
    eq(Pricing::defaultSize($small + ['photoSize' => 'S'])['code'], 'S');
    eq(Pricing::defaultSize($small + ['photoSize' => 'L'])['code'], 'M');
});
test('размеры от XS до XXL идут по порядку', function () use ($rose) {
    $one = fn ($code) => ['code' => $code, 'note' => '', 'packagingKop' => 0, 'items' => [['qty' => 1, 'flower' => $rose]]];
    eq(array_column(Pricing::availableSizes(['sizes' => [$one('XXL'), $one('M'), $one('XS')]]), 'code'), ['XS', 'M', 'XXL']);
});
test('букет скрывается, если ни один размер не собрать', function () use ($peony) {
    $sunny = ['enabled' => true, 'sizes' => [['code' => 'M', 'note' => '', 'packagingKop' => 800, 'items' => [['qty' => 7, 'flower' => $peony]]]]];
    eq(Pricing::visibility($sunny), ['visible' => false, 'reason' => 'no-flowers', 'missing' => ['Пион']]);
});
test('выключенный вручную букет скрыт', fn () => eq(Pricing::visibility(['enabled' => false] + $bag), ['visible' => false, 'reason' => 'disabled']));

group('замены и дробное количество');
$veronica = ['id' => 5, 'name' => 'Вероника', 'priceKop' => 500, 'inStock' => false, 'substitutes' => [['name' => 'Статица', 'inStock' => true]]];
$lonely = ['id' => 6, 'name' => 'Пион', 'priceKop' => 2000, 'inStock' => false, 'substitutes' => [['name' => 'Ранункулюс', 'inStock' => false]]];
test('нет цветка, но есть замена — размер остаётся, цена прежняя, в составе замена', function () use ($veronica, $rose) {
    $s = ['code' => 'M', 'note' => '', 'packagingKop' => 0, 'items' => [['qty' => 3, 'flower' => $veronica], ['qty' => 1, 'flower' => $rose]]];
    eq(Pricing::sizeAvailable($s), true);
    eq(Pricing::sizePriceKop($s), 2500);
    eq(Pricing::compositionText($s['items']), 'Статица (вместо: вероника) × 3, Роза кустовая 60 см × 1');
});
test('«будет замена»: для флориста и для пометки в админке', function () use ($veronica, $rose) {
    $s = ['code' => 'M', 'note' => '', 'packagingKop' => 0, 'items' => [['qty' => 3, 'flower' => $veronica], ['qty' => 1, 'flower' => $rose]]];
    eq(Pricing::swaps($s['items']), [['flower' => 'Вероника', 'to' => 'Статица']]);
    eq(Pricing::swapsText($s['items']), 'Статица вместо: вероника');
    eq(Pricing::swapsText([['qty' => 1, 'flower' => $rose]]), '', 'всё есть — замен нет');
    eq(Pricing::bouquetSwaps(['enabled' => true, 'sizes' => [$s, $s]]), [['flower' => 'Вероника', 'to' => 'Статица']], 'по букету — один раз');
});
test('в букете у цветка нет замены — размер скрыт, даже если букет многосоставной', function () use ($rose, $eustoma) {
    $noSwap = ['code' => 'M', 'note' => '', 'packagingKop' => 0, 'items' => [['qty' => 5, 'flower' => ['inStock' => false, 'substitutes' => []] + $eustoma], ['qty' => 3, 'flower' => $rose]]];
    eq(Pricing::sizeAvailable($noSwap), false);
    eq(Pricing::visibility(['enabled' => true, 'sizes' => [$noSwap]])['missing'], ['Эустома']);
});
test('замены тоже нет — размер скрыт', fn () => eq(Pricing::sizeAvailable(['code' => 'M', 'note' => '', 'packagingKop' => 0, 'items' => [['qty' => 1, 'flower' => $lonely]]]), false));
test('1,5 ветки эвкалипта: цена округляется до копейки', function () use ($eucalyptus) {
    $euc = ['priceKop' => 333] + $eucalyptus;
    eq(Pricing::sizePriceKop(['packagingKop' => 0, 'items' => [['qty' => 1.5, 'flower' => $euc]]]), 500);
    eq(Pricing::compositionText([['qty' => 1.5, 'flower' => $euc]]), 'Эвкалипт × 1,5');
});
test('цветков в размере (зелень не считаем)', fn () => eq(Pricing::flowerCount([['qty' => 5, 'flower' => ['kind' => 'FLOWER']], ['qty' => 2, 'flower' => ['kind' => 'GREEN']]]), 5));

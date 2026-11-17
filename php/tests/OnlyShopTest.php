<?php
// «Только из Уручья / Малиновки» (решение владельца 2026-11-15)
group('только из одного филиала');
$alstro = ['id' => 1, 'name' => 'Альстромерия белая', 'priceKop' => 500, 'inStock' => true, 'onlyShop' => 'uruchye'];
$carn = ['id' => 2, 'name' => 'Гвоздика розовая', 'priceKop' => 400, 'inStock' => true, 'onlyShop' => 'malinovka'];
$rose = ['id' => 3, 'name' => 'Роза', 'priceKop' => 1000, 'inStock' => true];
$codes = ['uruchye', 'malinovka'];
$size = fn (array ...$flowers) => ['code' => 'M', 'note' => '', 'packagingKop' => 0, 'items' => array_map(fn ($f) => ['qty' => 1, 'flower' => $f], $flowers)];

test('цветок только в Уручье без замены — размер только из Уручья', function () use ($size, $alstro, $rose, $codes) {
    eq(Pricing::sizeOnlyShop($size($rose, $alstro), '', $codes), 'uruchye');
    eq(Pricing::sizeAvailable($size($rose, $alstro)), true);
});
test('обычные цветы — из любого филиала', fn () => eq(Pricing::sizeOnlyShop($size($rose), '', $codes), ''));
test('есть замена в наличии, которая есть везде, — из любого филиала', function () use ($size, $alstro, $rose, $codes) {
    $a = $alstro + ['substitutes' => [['name' => 'Роза', 'inStock' => true]]];
    eq(Pricing::sizeOnlyShop($size($a), '', $codes), '');
});
test('замена тоже только в Уручье — всё равно только Уручье', function () use ($size, $alstro, $codes) {
    $a = $alstro + ['substitutes' => [['name' => 'Хризантема', 'inStock' => true, 'onlyShop' => 'uruchye']]];
    eq(Pricing::sizeOnlyShop($size($a), '', $codes), 'uruchye');
});
test('цветок закрыт, замена только в Малиновке — размер только из Малиновки', function () use ($size, $rose, $codes) {
    $r = ['inStock' => false, 'substitutes' => [['name' => 'Гвоздика', 'inStock' => true, 'onlyShop' => 'malinovka']]] + $rose;
    eq(Pricing::sizeOnlyShop($size($r), '', $codes), 'malinovka');
});
test('один цветок только в Уручье, другой только в Малиновке — размер не собрать', function () use ($size, $alstro, $carn) {
    eq(Pricing::sizeAvailable($size($alstro, $carn)), false);
});
test('галочка у самого букета важнее цветов', fn () => eq(Pricing::sizeOnlyShop($size($rose), 'malinovka', $codes), 'malinovka'));
test('флористу в Малиновке — замена для цветка «только из Уручья»', function () use ($size, $alstro) {
    $a = $alstro + ['substitutes' => [['name' => 'Роза', 'inStock' => true]]];
    eq(Pricing::swapsText($size($a)['items']), '');
    eq(Pricing::swapsText($size($a)['items'], 'uruchye'), '');
    eq(Pricing::swapsText($size($a)['items'], 'malinovka'), 'Роза вместо: альстромерия белая');
});
test('подписи: «Только из Уручья», «Только из Малиновки»', function () {
    $shops = [['code' => 'uruchye', 'name' => 'Уручье'], ['code' => 'malinovka', 'name' => 'Малиновка']];
    eq(Text::onlyShop('uruchye', $shops), 'Только из Уручья');
    eq(Text::onlyShop('malinovka', $shops), 'Только из Малиновки');
    eq(Text::onlyShop('', $shops), '');
    eq(Text::fromShop('Немига'), 'из Немиги');
});

group('доставка только из одного филиала');
$cfg = Defaults::DELIVERY;
$u = Delivery::fromShop($cfg, 'uruchye');
$m = Delivery::fromShop($cfg, 'malinovka');
test('Малиновка от Уручья — дальняя зона (25 BYN), а не 6 BYN от ближайшего', function () use ($cfg, $u) {
    $p = ['lat' => 53.851, 'lng' => 27.476];
    eq(Delivery::quote($p, 0, $cfg)['priceKop'], 600);
    eq(Delivery::quote($p, 0, $u)['priceKop'], 2500);
});
test('кнопка дальней зоны платная полностью независимо от суммы корзины', function () use ($u) {
    $last = count($u['zones']);
    eq(Delivery::quotePlace(['type' => 'zone', 'zone' => $last], 0, $u)['priceKop'], 2500);
    eq(Delivery::quotePlace(['type' => 'zone', 'zone' => $last], 30000, $u)['priceKop'], 2500);
    eq(Delivery::quotePlace(['type' => 'zone', 'zone' => 1], 30000, $u)['priceKop'], 0);
});
test('точка на карте в дальней зоне тоже платная полностью независимо от суммы корзины', function () use ($u) {
    $p = ['lat' => 53.851, 'lng' => 27.476];
    eq(Delivery::quote($p, 0, $u)['priceKop'], 2500);
    $q = Delivery::quote($p, 30000, $u);
    eq($q['priceKop'], 2500);
    eq($q['freeEligible'], false);
    eq($q['leftToFreeKop'], 0);
});
test('оба филиала: своя ближняя зона бесплатна, противоположный конец города платный', function () use ($u, $m) {
    $uruchye = ['lat' => 53.9452, 'lng' => 27.6882];
    $malinovka = ['lat' => 53.851, 'lng' => 27.476];
    eq(Delivery::quote($uruchye, 30000, $u)['priceKop'], 0, 'букет из Уручья — рядом с Уручьем бесплатно');
    eq(Delivery::quote($malinovka, 30000, $u)['priceKop'], 2500, 'букет из Уручья — Малиновка платно');
    eq(Delivery::quote($malinovka, 30000, $m)['priceKop'], 0, 'букет из Малиновки — рядом с Малиновкой бесплатно');
    eq(Delivery::quote($uruchye, 30000, $m)['priceKop'], 2500, 'букет из Малиновки — Уручье платно');
});
test('оба филиала: кнопка дальней красной зоны платная', function () use ($u, $m) {
    foreach ([$u, $m] as $delivery) {
        $last = count($delivery['zones']);
        eq(Delivery::quotePlace(['type' => 'zone', 'zone' => $last], 30000, $delivery)['priceKop'], 2500);
    }
});
test('оба филиала: зоны за МКАД после красной платные при любой сумме', function () use ($u, $m) {
    foreach ([$u, $m] as $delivery) {
        eq(Delivery::quotePlace(['type' => 'outside'], 0, $delivery)['priceKop'], 2500);
        eq(Delivery::quotePlace(['type' => 'outside'], 30000, $delivery)['priceKop'], 2500);
        eq(Delivery::quotePlace(['type' => 'outside', 'far' => true], 0, $delivery)['priceKop'], 3500);
        eq(Delivery::quotePlace(['type' => 'outside', 'far' => true], 30000, $delivery)['priceKop'], 3500);
    }
});
test('браузер может прислать far только как true', function () {
    eq(Checkout::parsePlace(['type' => 'outside', 'far' => true]), ['type' => 'outside', 'far' => true]);
    eq(Checkout::parsePlace(['type' => 'outside', 'far' => 1]), ['type' => 'outside']);
});

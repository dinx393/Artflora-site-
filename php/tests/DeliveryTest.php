<?php
group('доставка');
$at = function (string $name): array {
    foreach (Defaults::DISTRICTS as $d) if ($d['name'] === $name) return ['lat' => $d['lat'], 'lng' => $d['lng']];
    throw new RuntimeException("нет района $name");
};
$cfg = Defaults::DELIVERY;

test('возим до 30 км за МКАД, дальше — нет', function () use ($cfg) {
    eq(Delivery::quote(['lat' => 54.2279, 'lng' => 28.505], 13000, $cfg)['ok'], false); // Борисов
    eq(Delivery::quote(['lat' => 54.0285, 'lng' => 28.0886], 13000, $cfg)['ok'], true); // Смолевичи
});
test('рядом с магазином — первая зона, 6 BYN', function () use ($at, $cfg) {
    $q = Delivery::quote($at('Уручье'), 13000, $cfg);
    eq($q['zone'], 1);
    eq($q['priceKop'], 600);
    eq($q['shop']['code'], 'uruchye');
});
test('Малиновку везём из магазина на Чечота', fn () => eq(Delivery::quote($at('Малиновка'), 13000, $cfg)['shop']['code'], 'malinovka'));
test('центр — третья зона, 14 BYN (как в макете)', function () use ($at, $cfg) {
    $q = Delivery::quote($at('Центр'), 13000, $cfg);
    eq($q['priceKop'], 1400);
    eq($q['leftToFreeKop'], 7000);
});
test('за МКАД: 18 BYN + 1 BYN за км', function () use ($at, $cfg) {
    $q = Delivery::quote($at('Заславль'), 13000, $cfg);
    ok($q['ok'] && $q['outsideMkadKm'] > 0);
    eq($q['priceKop'], 1800 + $q['outsideMkadKm'] * 100);
});
test('бесплатно от 200 BYN внутри МКАД', fn () => eq(Delivery::quote($at('Центр'), 20000, $cfg)['priceKop'], 0));
test('за МКАД при бесплатной доставке остаётся доплата за км', function () use ($at, $cfg) {
    $q = Delivery::quote($at('Заславль'), 25000, $cfg);
    eq($q['priceKop'], $q['outsideMkadKm'] * 100);
});
test('слишком далеко — отказ', fn () => eq(Delivery::quote(['lat' => 53.13, 'lng' => 26.01], 13000, $cfg)['ok'], false));
test('самовывоз −10% на букеты', fn () => eq(Delivery::pickupDiscountKop(13000, $cfg), 1300));
test('свой участок важнее кругов: своя цена или «не возим»', function () use ($at, $cfg) {
    $box = fn ($lat, $lng, $d = 0.05) => [['lat' => $lat - $d, 'lng' => $lng - $d], ['lat' => $lat - $d, 'lng' => $lng + $d], ['lat' => $lat + $d, 'lng' => $lng + $d], ['lat' => $lat + $d, 'lng' => $lng - $d]];
    $c = $at('Центр');
    $cfg['areas'] = [
        ['name' => 'Смолевичи', 'color' => '#00aa00', 'priceKop' => 3500, 'noDelivery' => false, 'points' => $box(54.0285, 28.0886)],
        ['name' => 'Центр — не возим', 'color' => '#999999', 'priceKop' => 0, 'noDelivery' => true, 'points' => $box($c['lat'], $c['lng'], 0.01)],
    ];
    $smol = Delivery::quote(['lat' => 54.0285, 'lng' => 28.0886], 30000, $cfg);
    eq($smol['priceKop'], 3500);
    eq($smol['area']['name'], 'Смолевичи');
    eq(Delivery::quote($c, 13000, $cfg)['ok'], false);
    $u = Delivery::quote($at('Уручье'), 13000, $cfg);
    eq([$u['zone'], $u['priceKop']], [1, 600]);
});
test('доставка от 6 BYN', fn () => eq(Delivery::minKop($cfg), 600));

group('районы доставки');
test('у каждого района своя цена, по алфавиту', function () use ($cfg) {
    $list = Delivery::districtOptions(Defaults::DISTRICTS, $cfg);
    $by = array_column($list, 'priceKop', 'name');
    eq($by['Уручье'], 600);
    ok($by['Боровляны'] > 1800, 'Боровляны дороже 18 BYN');
    $names = array_column($list, 'name');
    $sorted = $names;
    usort($sorted, fn ($a, $b) => strcmp(str_replace('ё', 'е', mb_strtolower($a)), str_replace('ё', 'е', mb_strtolower($b))));
    eq($names, $sorted);
    eq($names[0], 'Боровляны');
});
test('слишком далёкие районы не показываем', fn () => eq(Delivery::districtOptions([['name' => 'Борисов', 'lat' => 54.2279, 'lng' => 28.505]], $cfg), []));

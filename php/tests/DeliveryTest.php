<?php
group('доставка');
$at = function (string $name): array {
    foreach (Defaults::DISTRICTS as $d) if ($d['name'] === $name) return ['lat' => $d['lat'], 'lng' => $d['lng']];
    throw new RuntimeException("нет района $name");
};
$cfg = Defaults::DELIVERY;

test('предела «до N км за МКАД» нет: дальность задают участки на карте', function () use ($cfg) {
    eq(Delivery::quote(['lat' => 54.2279, 'lng' => 28.505], 13000, $cfg)['priceKop'], $cfg['outsideMkadKop']); // Борисов
    $no = ['areas' => [['name' => 'Борисов', 'color' => '#999999', 'priceKop' => 0, 'noDelivery' => true,
        'points' => [['lat' => 54.1, 'lng' => 28.4], ['lat' => 54.3, 'lng' => 28.4], ['lat' => 54.3, 'lng' => 28.6], ['lat' => 54.1, 'lng' => 28.6]]]]] + $cfg;
    eq(Delivery::quote(['lat' => 54.2279, 'lng' => 28.505], 13000, $no)['ok'], false, 'участок «не возим»');
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
test('за МКАД — одна цена, без доплаты за км', function () use ($at, $cfg) {
    $q = Delivery::quote($at('Заславль'), 13000, $cfg);
    ok($q['ok'] && $q['outside'] && $q['outsideMkadKm'] > 0);
    eq($q['priceKop'], $cfg['outsideMkadKop']);
    eq(Delivery::quote(['lat' => 53.6, 'lng' => 27.4], 13000, $cfg)['priceKop'], $cfg['outsideMkadKop'], 'дальше — та же цена');
    eq(Delivery::quotePlace(['type' => 'outside'], 13000, $cfg)['priceKop'], $cfg['outsideMkadKop'], 'кнопка «За МКАД»');
});
test('бесплатно от 200 BYN внутри МКАД', fn () => eq(Delivery::quote($at('Центр'), 20000, $cfg)['priceKop'], 0));
test('за МКАД бесплатной доставки нет', fn () => eq(Delivery::quote($at('Заславль'), 25000, $cfg)['priceKop'], $cfg['outsideMkadKop']));
test('далеко за МКАД — та же цена «за МКАД»', fn () => eq(Delivery::quote(['lat' => 53.13, 'lng' => 26.01], 13000, $cfg)['priceKop'], $cfg['outsideMkadKop']));
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
test('районы на участке «не возим» не показываем', function () use ($cfg) {
    $cfg['areas'] = [['name' => 'Не возим', 'color' => '#999999', 'priceKop' => 0, 'noDelivery' => true,
        'points' => [['lat' => 54.1, 'lng' => 28.4], ['lat' => 54.3, 'lng' => 28.4], ['lat' => 54.3, 'lng' => 28.6], ['lat' => 54.1, 'lng' => 28.6]]]];
    eq(Delivery::districtOptions([['name' => 'Борисов', 'lat' => 54.2279, 'lng' => 28.505]], $cfg), []);
});
test('свой участок — пунктом в списке «куда везти» (Логойск)', function () use ($cfg) {
    $far = ['lat' => 54.206, 'lng' => 27.853]; // Логойск
    $cfg['areas'] = [
        ['name' => 'Удалённая доставка', 'color' => '#f2b705', 'priceKop' => 3500, 'noDelivery' => false,
            'points' => [['lat' => 54.33, 'lng' => 27.70], ['lat' => 54.33, 'lng' => 28.10], ['lat' => 54.12, 'lng' => 28.15], ['lat' => 54.05, 'lng' => 27.95], ['lat' => 54.10, 'lng' => 27.70]]],
        ['name' => 'Не возим', 'color' => '#999999', 'priceKop' => 0, 'noDelivery' => true,
            'points' => [['lat' => 53.6, 'lng' => 27.0], ['lat' => 53.6, 'lng' => 27.1], ['lat' => 53.7, 'lng' => 27.1]]],
    ];
    eq(Delivery::quote($far, 0, $cfg)['priceKop'], 3500);
    $places = Delivery::areaPlaces($cfg);
    eq(array_column($places, 'name'), ['Удалённая доставка']);
    eq(Delivery::areaAt($places[0], $cfg)['name'], 'Удалённая доставка');
    $opts = Delivery::districtOptions([['name' => 'Центр', 'lat' => 53.9006, 'lng' => 27.559]], $cfg);
    eq(array_column($opts, 'name'), ['Центр', 'Удалённая доставка']);
    eq([$opts[1]['priceKop'], $opts[1]['isArea'] ?? false], [3500, true]);
    // вогнутый участок («подкова»): центр снаружи, точку всё равно находим внутри
    $cfg['areas'] = [['name' => 'Подкова', 'color' => '#f00', 'priceKop' => 2000, 'noDelivery' => false, 'points' => [
        ['lat' => 54.0, 'lng' => 27.0], ['lat' => 54.3, 'lng' => 27.0], ['lat' => 54.3, 'lng' => 27.1], ['lat' => 54.1, 'lng' => 27.1],
        ['lat' => 54.1, 'lng' => 27.4], ['lat' => 54.3, 'lng' => 27.4], ['lat' => 54.3, 'lng' => 27.5], ['lat' => 54.0, 'lng' => 27.5]]]];
    eq(Delivery::areaAt(Delivery::areaPlaces($cfg)[0], $cfg)['name'], 'Подкова');
});
test('выбор в корзине: кнопка зоны, участок, точка на карте', function () use ($cfg) {
    $cfg['areas'] = [['name' => 'Удалённая доставка', 'color' => '#f2b705', 'priceKop' => 6000, 'noDelivery' => false,
        'points' => [['lat' => 54.33, 'lng' => 27.70], ['lat' => 54.33, 'lng' => 28.10], ['lat' => 54.12, 'lng' => 28.15], ['lat' => 54.05, 'lng' => 27.95], ['lat' => 54.10, 'lng' => 27.70]]]];
    $z2 = Delivery::quotePlace(['type' => 'zone', 'zone' => 2], 13000, $cfg);
    eq([$z2['ok'], $z2['priceKop'], $z2['shop']], [true, 1000, null]);
    eq(Delivery::quotePlace(['type' => 'zone', 'zone' => 2], 20000, $cfg)['priceKop'], 0, 'бесплатно от 200 BYN');
    eq(Delivery::quotePlace(['type' => 'zone', 'zone' => 9], 0, $cfg)['ok'], false);
    eq(Delivery::quotePlace(['type' => 'area', 'name' => 'Удалённая доставка'], 30000, $cfg)['priceKop'], 6000);
    eq(Delivery::quotePlace(['type' => 'area', 'name' => 'Нет такого'], 0, $cfg)['ok'], false);
    $logoisk = ['type' => 'point', 'lat' => 54.206, 'lng' => 27.853];
    $q = Delivery::quotePlace($logoisk, 0, $cfg);
    eq([$q['priceKop'], $q['area']['name']], [6000, 'Удалённая доставка']);
    eq(Delivery::placeLabel($logoisk, $q, $cfg), 'Удалённая доставка, точка на карте 54.20600, 27.85300');
    eq(Delivery::placeLabel(['type' => 'zone', 'zone' => 1], $z2, $cfg), 'Зона 1 (до 3 км от магазина)');
});

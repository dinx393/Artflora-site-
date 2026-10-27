<?php
// Расчёт доставки. Чистые функции, без базы и сети, чтобы их легко проверять тестами.
//
// Правила (из макетов):
//  - везём из ближайшего магазина;
//  - зоны по расстоянию от магазина: 6 / 10 / 14 / 18 BYN;
//  - за МКАД: одна цена outsideMkadKop (решение владельца 2026-10-23, без доплаты за км), бесплатной доставки там нет;
//  - бесплатно от суммы freeFromKop (внутри МКАД);
//  - самовывоз: скидка pickupDiscountPct на букеты;
//  - свои участки (нарисованы в админке на карте) важнее кругов: там своя цена или «не возим».
//
// Настройки — массив как Defaults::DELIVERY: shops, zones, mkadCenter, mkadRadiusKm, outsideMkadKop,
// freeFromKop, pickupDiscountPct, outsideColor?, areas?.
// Предела «до N км за МКАД» нет (владелец, 2026-10-25): куда не возим, задают участки «не возим» на карте. Точка — ['lat' => .., 'lng' => ..].
final class Delivery
{
    // Точка внутри многоугольника (луч вправо, считаем пересечения). Для участков в пару десятков км плоской карты хватает.
    public static function insidePolygon(array $p, array $poly): bool
    {
        $inside = false;
        $n = count($poly);
        for ($i = 0, $j = $n - 1; $i < $n; $j = $i++) {
            $a = $poly[$i];
            $b = $poly[$j];
            if ((($a['lat'] > $p['lat']) !== ($b['lat'] > $p['lat']))
                && $p['lng'] < ($b['lng'] - $a['lng']) * ($p['lat'] - $a['lat']) / ($b['lat'] - $a['lat']) + $a['lng']) {
                $inside = !$inside;
            }
        }
        return $inside;
    }

    public static function areaAt(array $point, array $cfg): ?array
    {
        foreach ($cfg['areas'] ?? [] as $a) {
            if (count($a['points']) >= 3 && self::insidePolygon($point, $a['points'])) return $a;
        }
        return null;
    }

    public static function distanceKm(array $a, array $b): float
    {
        $r = 6371;
        $dLat = deg2rad($b['lat'] - $a['lat']);
        $dLng = deg2rad($b['lng'] - $a['lng']);
        $h = sin($dLat / 2) ** 2 + cos(deg2rad($a['lat'])) * cos(deg2rad($b['lat'])) * sin($dLng / 2) ** 2;
        return 2 * $r * asin(sqrt($h));
    }

    // ['shop' => магазин, 'km' => до него]
    public static function nearestShop(array $point, array $shops): array
    {
        $best = null;
        foreach ($shops as $shop) {
            $km = self::distanceKm($point, $shop['location']);
            if ($best === null || $km < $best['km']) $best = ['shop' => $shop, 'km' => $km];
        }
        return $best;
    }

    // Удачно: ['ok' => true, 'shop', 'km' (до магазина), 'zone' (1..N, за МКАД — последняя; 0 — свой участок),
    //          'outsideMkadKm', 'baseKop' (без бесплатной), 'priceKop' (к оплате), 'free', 'leftToFreeKop', 'area'? => ['name','color']]
    // Не возим (участок «не возим»): ['ok' => false, 'reason' => 'too-far', 'km' => 0, 'area']
    public static function quote(array $point, int $subtotalKop, array $cfg): array
    {
        ['shop' => $shop, 'km' => $km] = self::nearestShop($point, $cfg['shops']);
        $area = self::areaAt($point, $cfg);
        if ($area) {
            $tag = ['name' => $area['name'], 'color' => $area['color']];
            if (!empty($area['noDelivery'])) return ['ok' => false, 'reason' => 'too-far', 'km' => 0, 'area' => $tag];
            // Цена участка фиксированная; бесплатная доставка от суммы на участки не действует (они обычно за городом).
            // Карта «из филиала» (fromShop): со стороны другого филиала дороже на farExtraKop.
            $far = !empty($area['farExtraKop']) && self::farSide($point, $km, $cfg);
            $kop = (int) $area['priceKop'] + ($far ? (int) $area['farExtraKop'] : 0);
            return [
                'ok' => true, 'shop' => $shop, 'km' => round($km, 1), 'zone' => 0, 'outsideMkadKm' => 0,
                'baseKop' => $kop, 'priceKop' => $kop, 'free' => false, 'leftToFreeKop' => 0,
                'area' => $tag + ($far ? ['farSide' => true] : []),
            ];
        }
        $fromCenter = self::distanceKm($point, $cfg['mkadCenter']);
        $outside = (int) max(0, ceil($fromCenter - $cfg['mkadRadiusKm']));
        $last = count($cfg['zones']) - 1;

        $zoneIdx = -1;
        foreach ($cfg['zones'] as $i => $z) {
            if ($km <= $z['maxKm']) { $zoneIdx = $i; break; }
        }
        if ($zoneIdx === -1 || $outside > 0) $zoneIdx = $last;

        if ($outside > 0) return self::outsideQuote($shop, round($km, 1), $outside, $cfg, self::farSide($point, $km, $cfg));
        $baseKop = (int) $cfg['zones'][$zoneIdx]['priceKop'];
        $free = $subtotalKop >= $cfg['freeFromKop'];
        return [
            'ok' => true, 'shop' => $shop, 'km' => round($km, 1), 'zone' => $zoneIdx + 1, 'outsideMkadKm' => 0,
            'baseKop' => $baseKop, 'priceKop' => $free ? (int) ($cfg['zones'][$zoneIdx]['freeKop'] ?? 0) : $baseKop, 'free' => $free,
            'leftToFreeKop' => max(0, $cfg['freeFromKop'] - $subtotalKop),
        ];
    }

    // За МКАД — одна цена, без бесплатной доставки. outsideMkadKm — сколько км за МКАД (для флориста), на цену не влияет.
    // farSide — заказ оформлен в филиале (fromShop), а точка ближе к другому филиалу: дороже на outsideExtraKop.
    private static function outsideQuote(?array $shop, ?float $km, int $outsideKm, array $cfg, bool $farSide = false): array
    {
        $kop = (int) $cfg['outsideMkadKop'] + ($farSide ? (int) $cfg['shopOrder']['outsideExtraKop'] : 0);
        return [
            'ok' => true, 'shop' => $shop, 'km' => $km, 'zone' => count($cfg['zones']), 'outsideMkadKm' => $outsideKm, 'outside' => true,
            'baseKop' => $kop, 'priceKop' => $kop, 'free' => false, 'leftToFreeKop' => 0, 'farSide' => $farSide,
        ];
    }

    private static function farSide(array $point, float $km, array $cfg): bool
    {
        foreach ($cfg['otherShops'] ?? [] as $loc) {
            if (self::distanceKm($point, $loc) < $km) return true;
        }
        return false;
    }

    // Доставка, оформленная прямо в филиале $code (решение владельца 2026-10-27). Только для пояснения на странице
    // «Доставка и оплата»: корзина по-прежнему считает от ближайшего магазина. Букет едет из этого филиала, поэтому
    // до shopOrder.farFromKm — зоны сайта, дальше по Минску — farKop (при бесплатной доставке — только разница с последней
    // зоной сайта); со стороны другого филиала за МКАД дороже на outsideExtraKop, на своих участках — на areaExtraKop.
    // Ответ — настройки доставки, как для quote(): с одним магазином и otherShops. У участков — farExtraKop и
    // sides: [near, far] — есть ли у участка часть со стороны этого / другого филиала (для подписей под картой).
    public static function fromShop(array $cfg, string $code): array
    {
        $o = $cfg['shopOrder'];
        $shop = null;
        $others = [];
        foreach ($cfg['shops'] as $s) {
            if ($s['code'] === $code) $shop = $s;
            else $others[] = $s['location'];
        }
        if (!$shop) throw new InvalidArgumentException("Нет магазина $code");
        $zones = $cfg['zones'];
        $last = array_pop($zones);
        $zones = array_values(array_filter($zones, fn ($z) => $z['maxKm'] < $o['farFromKm']));
        $zones[] = ['maxKm' => $o['farFromKm']] + $last;
        $zones[] = ['maxKm' => 999, 'priceKop' => $o['farKop'], 'color' => $o['farColor'], 'freeKop' => max(0, $o['farKop'] - (int) $last['priceKop'])];
        $areas = [];
        foreach ($cfg['areas'] ?? [] as $a) {
            if (empty($a['noDelivery']) && count($a['points']) >= 3) {
                $a['farExtraKop'] = (int) $o['areaExtraKop'];
                $sides = [false => false, true => false];
                foreach ($a['points'] as $p) $sides[self::farSide($p, self::distanceKm($p, $shop['location']), ['otherShops' => $others])] = true;
                $a['sides'] = [$sides[false], $sides[true]];
            }
            $areas[] = $a;
        }
        return ['shops' => [$shop], 'otherShops' => $others, 'zones' => $zones, 'areas' => $areas] + $cfg;
    }

    // Цена по выбору покупателя в корзине (решение владельца 2026-10-23: без длинного списка районов).
    // place: ['type' => 'point', 'lat', 'lng'] — место, отмеченное на карте (считаем как обычно, по точке);
    //        ['type' => 'zone', 'zone' => N]  — кнопка цветной зоны: её цена, магазин выберет флорист;
    //        ['type' => 'outside']             — кнопка «За МКАД»;
    //        ['type' => 'area', 'name']        — кнопка своего участка с карты.
    // Ответ как у quote(); у зоны и участка 'shop' => null и 'km' => null.
    public static function quotePlace(array $place, int $subtotalKop, array $cfg): array
    {
        $far = ['ok' => false, 'reason' => 'too-far', 'km' => 0];
        switch ($place['type']) {
            case 'point':
                return self::quote($place, $subtotalKop, $cfg);
            case 'zone':
                $z = $cfg['zones'][$place['zone'] - 1] ?? null;
                if (!$z) return $far;
                $base = (int) $z['priceKop'];
                $free = $subtotalKop >= $cfg['freeFromKop'];
                return [
                    'ok' => true, 'shop' => null, 'km' => null, 'zone' => $place['zone'], 'outsideMkadKm' => 0,
                    'baseKop' => $base, 'priceKop' => $free ? 0 : $base, 'free' => $free, 'leftToFreeKop' => max(0, $cfg['freeFromKop'] - $subtotalKop),
                ];
            case 'outside':
                return self::outsideQuote(null, null, 0, $cfg);
            case 'area':
                foreach ($cfg['areas'] ?? [] as $a) {
                    if ($a['name'] !== $place['name'] || count($a['points']) < 3) continue;
                    $tag = ['name' => $a['name'], 'color' => $a['color']];
                    if (!empty($a['noDelivery'])) return $far + ['area' => $tag];
                    return [
                        'ok' => true, 'shop' => null, 'km' => null, 'zone' => 0, 'outsideMkadKm' => 0,
                        'baseKop' => (int) $a['priceKop'], 'priceKop' => (int) $a['priceKop'], 'free' => false, 'leftToFreeKop' => 0, 'area' => $tag,
                    ];
                }
                return $far;
        }
        return $far;
    }

    // Подпись зоны для покупателя: «до 3 км от магазина», последняя — «дальше, до МКАД»
    public static function zoneTitle(int $zone, array $cfg): string
    {
        $last = count($cfg['zones']);
        return $zone === $last ? 'дальше, до МКАД' : 'до ' . $cfg['zones'][$zone - 1]['maxKm'] . ' км от магазина';
    }

    // Как выбор покупателя видят флорист и курьер в адресе заказа
    public static function placeLabel(array $place, array $quote, array $cfg): string
    {
        if (!empty($place['district'])) return $place['district'];
        if ($place['type'] === 'zone') return 'Зона ' . $place['zone'] . ' (' . self::zoneTitle($place['zone'], $cfg) . ')';
        if ($place['type'] === 'area') return $place['name'];
        if ($place['type'] === 'outside') return 'За МКАД';
        $at = sprintf('точка на карте %.5f, %.5f', $place['lat'], $place['lng']);
        if (isset($quote['area'])) return $quote['area']['name'] . ', ' . $at;
        if (!empty($quote['outside'])) return "За МКАД ({$quote['outsideMkadKm']} км), $at";
        return 'Зона ' . ($quote['zone'] ?? '?') . ', ' . $at;
    }

    public static function pickupDiscountKop(int $bouquetsKop, array $cfg): int
    {
        return (int) round($bouquetsKop * $cfg['pickupDiscountPct'] / 100);
    }

    // Минимальная цена доставки — для надписи «Доставка от 6 BYN».
    public static function minKop(array $cfg): int
    {
        return min(array_map(fn ($z) => (int) $z['priceKop'], $cfg['zones']));
    }

    // Свои участки как пункты списка «куда везти» (решение владельца 2026-10-23: например, жёлтая «Удалённая доставка»
    // с Логойском — в списке районов его нет, а выбрать надо). Точка — любая внутри участка, по ней считается цена.
    // Участки «не возим» и участки, у которых имя совпало с районом, пропускаем.
    public static function areaPlaces(array $cfg, array $districts = []): array
    {
        $taken = array_map(fn ($d) => $d['name'], $districts);
        $out = [];
        foreach ($cfg['areas'] ?? [] as $a) {
            if (!empty($a['noDelivery']) || count($a['points']) < 3 || in_array($a['name'], $taken, true)) continue;
            $p = self::pointInArea($a, $cfg);
            if ($p) $out[] = ['name' => $a['name'], 'lat' => $p['lat'], 'lng' => $p['lng']];
        }
        return $out;
    }

    // Точка внутри участка, где действует именно его цена: центр, иначе середины от первой вершины к остальным
    private static function pointInArea(array $area, array $cfg): ?array
    {
        $pts = $area['points'];
        $n = count($pts);
        $cands = [['lat' => array_sum(array_column($pts, 'lat')) / $n, 'lng' => array_sum(array_column($pts, 'lng')) / $n]];
        for ($i = 2; $i < $n; $i++) {
            $cands[] = ['lat' => ($pts[0]['lat'] + $pts[$i - 1]['lat'] + $pts[$i]['lat']) / 3, 'lng' => ($pts[0]['lng'] + $pts[$i - 1]['lng'] + $pts[$i]['lng']) / 3];
        }
        foreach ($cands as $p) {
            $hit = self::areaAt($p, $cfg);
            if ($hit && $hit['name'] === $area['name']) return ['lat' => round($p['lat'], 5), 'lng' => round($p['lng'], 5)];
        }
        return null;
    }

    // Район доставки с ценой для списка в корзине и на странице букета:
    // ['name','lat','lng','zone','priceKop','shopName','area'?, 'isArea'?]. Районы дальше разрешённого не показываем.
    // После районов (по алфавиту) идут свои участки с карты, с пометкой isArea.
    // В корзине цена — обычная (subtotalKop = 0), бесплатную доставку от суммы корзина покажет сама.
    public static function districtOptions(array $list, array $cfg, int $subtotalKop = 0): array
    {
        $out = [];
        foreach ($list as $d) {
            $q = self::quote($d, $subtotalKop, $cfg);
            if (!$q['ok']) continue;
            $o = ['name' => $d['name'], 'lat' => $d['lat'], 'lng' => $d['lng'], 'zone' => $q['zone'], 'priceKop' => $q['priceKop'], 'shopName' => $q['shop']['name']];
            if (isset($q['area'])) $o['area'] = $q['area']['name'];
            $out[] = $o;
        }
        $coll = class_exists('Collator') ? new Collator('ru_RU') : null;
        usort($out, fn ($a, $b) => $coll ? $coll->compare($a['name'], $b['name']) : strcmp(self::sortKey($a['name']), self::sortKey($b['name'])));
        foreach (self::areaPlaces($cfg, $list) as $p) {
            $q = self::quote($p, $subtotalKop, $cfg);
            if ($q['ok']) $out[] = ['name' => $p['name'], 'lat' => $p['lat'], 'lng' => $p['lng'], 'zone' => 0, 'priceKop' => $q['priceKop'], 'shopName' => $q['shop']['name'], 'area' => $p['name'], 'isArea' => true];
        }
        return $out;
    }

    // Без расширения intl: «ё» сортируем как «е», регистр не важен.
    private static function sortKey(string $s): string
    {
        return str_replace(['ё', 'Ё'], ['е', 'е'], mb_strtolower($s));
    }
}

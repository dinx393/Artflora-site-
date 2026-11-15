<?php
// Цена размера = Σ (розничная цена цветка × количество) + упаковка этого размера.
// Размер доступен, только если все его цветы есть в наличии или для них есть замена в наличии.
// Замены задаются в каждом букете для каждого цветка свои (таблица bouquet_swaps, решение владельца 2026-10-31):
// нет замены в этом букете — без цветка размер не собрать, даже если букет многосоставной.
// Количество бывает дробным (1,5 ветки), цена позиции округляется до копейки.
// Букет показываем, если он включён и у него остался хотя бы один доступный размер.
//
// Формы данных (массивы):
//   цветок  ['id','name','priceKop','inStock', 'kind'?, 'onlyShop'?, 'substitutes' => [['name','inStock','onlyShop'?], ...]?]  — замены в этом букете
//   onlyShop — код филиала, где только и есть этот цветок ('' или нет — есть везде; решение владельца 2026-11-15)
//   позиция ['qty' => 1.5, 'flower' => цветок]
//   размер  ['code' => 'M', 'note' => '', 'packagingKop' => 0, 'items' => [позиция, ...]]
//   букет   ['enabled' => true, 'photoSize'? => 'M', 'sizes' => [размер, ...]]
final class Pricing
{
    // Размеры по порядку. Незаполненные (без цветов) на сайте не показываются.
    public const SIZE_ORDER = ['XS', 'S', 'M', 'L', 'XL', 'XXL'];

    public static function itemKop(array $i): int
    {
        return (int) round($i['flower']['priceKop'] * $i['qty']);
    }

    public static function flowersKop(array $items): int
    {
        return array_sum(array_map([self::class, 'itemKop'], $items));
    }

    // Чем собираем: сам цветок, если есть (false); иначе первая замена в наличии (её название); null — собрать нечем.
    // Цена при замене остаётся ценой исходного цветка, чтобы цена букета не прыгала.
    // $shop — филиал, который собирает заказ (для флориста): цветок, который есть только в другом филиале, там не взять.
    public static function replacementFor(array $f, string $shop = ''): string|null|false
    {
        $here = fn (array $x) => !empty($x['inStock']) && ($shop === '' || ($x['onlyShop'] ?? '') === '' || $x['onlyShop'] === $shop);
        if ($here($f)) return false;
        foreach ($f['substitutes'] ?? [] as $x) {
            if ($here($x)) return $x['name'];
        }
        return null;
    }

    // Цена для покупателя — «красивая»: вверх до 5 или 0 BYN (177 → 180, 93 → 95, 120 → 120). Решение владельца 2026-10-23.
    public const ROUND_KOP = 500;

    public static function roundUpKop(int $kop): int
    {
        return (int) (ceil($kop / self::ROUND_KOP) * self::ROUND_KOP);
    }

    // Сумма по составу без округления — чтобы флорист в админке видел, откуда цена
    public static function rawSizeKop(array $size): int
    {
        return self::flowersKop($size['items']) + (int) $size['packagingKop'];
    }

    public static function sizePriceKop(array $size): int
    {
        return self::roundUpKop(self::rawSizeKop($size));
    }

    // Где можно собрать позицию (решение владельца 2026-11-15): null — в любом филиале; ['uruchye'] — только там;
    // [] — нигде (нет ни цветка, ни замены). Цветок только в одном филиале, но в букете есть замена в наличии,
    // которая есть везде, — позицию соберут где угодно (в другом филиале с заменой).
    public static function itemShops(array $f): ?array
    {
        $opts = [];
        if (!empty($f['inStock'])) $opts[] = (string) ($f['onlyShop'] ?? '');
        foreach ($f['substitutes'] ?? [] as $x) if (!empty($x['inStock'])) $opts[] = (string) ($x['onlyShop'] ?? '');
        if (!$opts) return [];
        if (in_array('', $opts, true)) return null;
        return array_values(array_unique($opts));
    }

    // Где можно собрать размер: пересечение по всем позициям. null — везде, [] — нигде (цветы в разных филиалах).
    public static function sizeShops(array $size): ?array
    {
        $set = null;
        foreach ($size['items'] as $i) {
            $s = self::itemShops($i['flower']);
            if ($s !== null) $set = $set === null ? $s : array_values(array_intersect($set, $s));
        }
        return $set;
    }

    // Из какого одного филиала только и можно заказать размер: '' — из любого. $bouquetOnly — галочка у самого букета
    // (важнее цветов), $shopCodes — все магазины из настроек: если размер собирают во всех, ограничения нет.
    public static function sizeOnlyShop(array $size, string $bouquetOnly = '', array $shopCodes = []): string
    {
        if ($bouquetOnly !== '') return $bouquetOnly;
        $set = self::sizeShops($size);
        if ($set === null || !$set) return '';
        if ($shopCodes) {
            $set = array_values(array_intersect($set, $shopCodes));
            if (count($set) === count($shopCodes)) return '';
        }
        return count($set) === 1 ? $set[0] : '';
    }

    public static function missingFlowers(array $size): array
    {
        $out = [];
        foreach ($size['items'] as $i) {
            if (self::replacementFor($i['flower']) === null) $out[] = $i['flower']['name'];
        }
        return $out;
    }

    public static function sizeAvailable(array $size): bool
    {
        // цветы есть, но в разных филиалах (один только в Уручье, другой только в Малиновке) — не собрать
        return count($size['items']) > 0 && !self::missingFlowers($size) && self::sizeShops($size) !== [];
    }

    public static function sortSizes(array $sizes): array
    {
        usort($sizes, fn ($a, $b) => array_search($a['code'], self::SIZE_ORDER, true) <=> array_search($b['code'], self::SIZE_ORDER, true));
        return $sizes;
    }

    public static function availableSizes(array $b): array
    {
        return self::sortSizes(array_values(array_filter($b['sizes'], [self::class, 'sizeAvailable'])));
    }

    // Размер «по умолчанию» — тот, что на фото (владелец отмечает в админке), иначе M, иначе первый доступный.
    public static function defaultSize(array $b): ?array
    {
        $av = self::availableSizes($b);
        foreach ([$b['photoSize'] ?? null, 'M'] as $code) {
            foreach ($av as $s) if ($s['code'] === $code) return $s;
        }
        return $av[0] ?? null;
    }

    // ['visible' => true] | ['visible' => false, 'reason' => 'disabled'] | ['visible' => false, 'reason' => 'no-flowers', 'missing' => [...]]
    public static function visibility(array $b): array
    {
        if (empty($b['enabled'])) return ['visible' => false, 'reason' => 'disabled'];
        if (self::availableSizes($b)) return ['visible' => true];
        $missing = [];
        foreach ($b['sizes'] as $s) foreach (self::missingFlowers($s) as $m) $missing[$m] = true;
        return ['visible' => false, 'reason' => 'no-flowers', 'missing' => array_keys($missing)];
    }

    // 1.5 → «1,5»
    public static function qtyText(int|float $q): string
    {
        $r = round($q * 100) / 100;
        return str_replace('.', ',', (string) ($r == (int) $r ? (int) $r : $r));
    }

    public static function compositionText(array $items): string
    {
        return implode(', ', array_map(function ($i) {
            $f = $i['flower'];
            $sub = array_key_exists('inStock', $f) && !$f['inStock'] ? self::replacementFor(['inStock' => false, 'substitutes' => $f['substitutes'] ?? []]) : false;
            return $sub
                ? "$sub (вместо: " . mb_strtolower($f['name']) . ') × ' . self::qtyText($i['qty'])
                : $f['name'] . ' × ' . self::qtyText($i['qty']);
        }, $items));
    }

    // Какие цветы сейчас заменяем: [['flower' => 'Альстромерия белая', 'to' => 'Альстромерия розовая'], ...]
    public static function swaps(array $items, string $shop = ''): array
    {
        $out = [];
        foreach ($items as $i) {
            $to = self::replacementFor($i['flower'], $shop);
            if (is_string($to)) $out[] = ['flower' => $i['flower']['name'], 'to' => $to];
        }
        return $out;
    }

    // Для флориста в заказе: «Альстромерия розовая вместо: альстромерия белая; …» (пусто — замен нет)
    public static function swapsText(array $items, string $shop = ''): string
    {
        return implode('; ', array_map(fn ($x) => $x['to'] . ' вместо: ' . mb_strtolower($x['flower']), self::swaps($items, $shop)));
    }

    // Замены по всему букету (все размеры, что можно собрать) — для пометки «будет замена» в админке
    public static function bouquetSwaps(array $b): array
    {
        $out = [];
        foreach (self::availableSizes($b) as $s) foreach (self::swaps($s['items']) as $x) $out[$x['flower']] = $x;
        return array_values($out);
    }

    // Сколько всего цветков в размере, для подписи «11 цветков» (зелень не считаем).
    public static function flowerCount(array $items): int
    {
        $n = 0;
        foreach ($items as $i) if (($i['flower']['kind'] ?? '') !== 'GREEN') $n += $i['qty'];
        return (int) round($n);
    }
}

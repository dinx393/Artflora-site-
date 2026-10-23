<?php
// Цена размера = Σ (розничная цена цветка × количество) + упаковка этого размера.
// Размер доступен, только если все его цветы есть в наличии или для них есть замена в наличии.
// Количество бывает дробным (1,5 ветки), цена позиции округляется до копейки.
// Букет показываем, если он включён и у него остался хотя бы один доступный размер.
//
// Формы данных (массивы):
//   цветок  ['id','name','priceKop','inStock', 'kind'?, 'substitutes' => [['name','inStock'], ...]?]
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
    public static function replacementFor(array $f): string|null|false
    {
        if (!empty($f['inStock'])) return false;
        foreach ($f['substitutes'] ?? [] as $x) {
            if (!empty($x['inStock'])) return $x['name'];
        }
        return null;
    }

    public static function sizePriceKop(array $size): int
    {
        return self::flowersKop($size['items']) + (int) $size['packagingKop'];
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
        return count($size['items']) > 0 && !self::missingFlowers($size);
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

    // Сколько всего цветков в размере, для подписи «11 цветков» (зелень не считаем).
    public static function flowerCount(array $items): int
    {
        $n = 0;
        foreach ($items as $i) if (($i['flower']['kind'] ?? '') !== 'GREEN') $n += $i['qty'];
        return (int) round($n);
    }
}

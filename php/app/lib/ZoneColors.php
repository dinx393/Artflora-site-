<?php
// Цвета зон доставки на карте в админке: зона 1 — синяя, 2 — оранжевая и т.д. (как привычно по Flowwow).
final class ZoneColors
{
    public const ZONES = ['#4f8ef7', '#f5892b', '#2fb36d', '#2cc6e8', '#ef5d6c', '#f2b705', '#8f6ed5', '#d45fb4', '#7f8c3a', '#5c6bc0'];
    // За МКАД — отдельный цвет: цена там растёт с каждым км.
    public const OUTSIDE = '#a98be8';

    // Цвет зоны: свой, если владелец выбрал, иначе из набора по номеру.
    public static function zone(int $zone, ?string $own = null): string
    {
        return $own ?: self::ZONES[($zone - 1) % count(self::ZONES)];
    }

    // Цвет для новой зоны или участка: первый из набора, которого ещё нет.
    public static function next(array $used): string
    {
        $taken = array_map('strtolower', $used);
        foreach ([...self::ZONES, '#e57373', '#26a69a', '#ffb74d', '#9575cd', '#4db6ac', '#a1887f'] as $c) {
            if (!in_array($c, $taken, true)) return $c;
        }
        return sprintf('#%06x', random_int(0, 0xffffff));
    }

    // Цвет точки на карте для расчёта: участок, за МКАД, зона; серый — не возим.
    public static function forQuote(array $q, array $zones, ?string $outsideColor = null): string
    {
        if (isset($q['area'])) return $q['area']['color'];
        if (!$q['ok']) return '#999999';
        if (!empty($q['outsideMkadKm'])) return $outsideColor ?: self::OUTSIDE;
        return self::zone($q['zone'], $zones[$q['zone'] - 1]['color'] ?? null);
    }
}

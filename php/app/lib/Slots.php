<?php
// Интервалы доставки и самовывоза. Время — минское (UTC+3, без перехода на летнее).
final class Slots
{
    // Интервал — час, новый начинается каждые полчаса: 9:00–10:00, 9:30–10:30 … 20:00–21:00 (решение владельца 2026-10-23)
    public const ALL = [
        '9:00–10:00', '9:30–10:30', '10:00–11:00', '10:30–11:30', '11:00–12:00', '11:30–12:30',
        '12:00–13:00', '12:30–13:30', '13:00–14:00', '13:30–14:30', '14:00–15:00', '14:30–15:30',
        '15:00–16:00', '15:30–16:30', '16:00–17:00', '16:30–17:30', '17:00–18:00', '17:30–18:30',
        '18:00–19:00', '18:30–19:30', '19:00–20:00', '19:30–20:30', '20:00–21:00',
    ];

    // ['date' => 'YYYY-MM-DD', 'hour' => 11, 'minute' => 30]; $now — метка времени (секунды)
    public static function minskNow(?int $now = null): array
    {
        $t = ($now ?? time()) + 3 * 3600;
        return ['date' => gmdate('Y-m-d', $t), 'hour' => (int) gmdate('G', $t), 'minute' => (int) gmdate('i', $t)];
    }

    // «9:30–10:30» → 9.5
    public static function startHour(string $slot): float
    {
        [$h, $m] = array_map('intval', explode(':', explode('–', $slot)[0]) + [1 => 0]);
        return $h + $m / 60;
    }

    public static function addDays(string $isoDate, int $days): string
    {
        return gmdate('Y-m-d', strtotime($isoDate . ' 00:00:00 UTC') + $days * 86400);
    }

    // Какие интервалы ещё можно выбрать на дату. Сегодня — только те,
    // что начинаются не раньше чем через leadHours (время собрать букет и довезти).
    public static function available(string $isoDate, int|float $leadHours, ?int $now = null): array
    {
        $m = self::minskNow($now);
        if ($isoDate < $m['date']) return [];
        if ($isoDate > $m['date']) return self::ALL;
        $ready = $m['hour'] + $m['minute'] / 60 + $leadHours;
        return array_values(array_filter(self::ALL, fn ($s) => self::startHour($s) >= $ready));
    }

    // Можно ли ещё привезти в этот день хоть в какой-то интервал (когда время уточним у получателя).
    public static function isDateAllowed(string $isoDate, int|float $leadHours, ?int $now = null): bool
    {
        return (bool) self::available($isoDate, $leadHours, $now);
    }

    public static function isSlotAllowed(string $isoDate, string $slot, int|float $leadHours, ?int $now = null): bool
    {
        return in_array($slot, self::available($isoDate, $leadHours, $now), true);
    }
}

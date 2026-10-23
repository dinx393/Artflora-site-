<?php
// Интервалы доставки и самовывоза. Время — минское (UTC+3, без перехода на летнее).
final class Slots
{
    public const ALL = ['10–12', '12–14', '14–16', '16–18', '18–21'];

    // ['date' => 'YYYY-MM-DD', 'hour' => 11, 'minute' => 30]; $now — метка времени (секунды)
    public static function minskNow(?int $now = null): array
    {
        $t = ($now ?? time()) + 3 * 3600;
        return ['date' => gmdate('Y-m-d', $t), 'hour' => (int) gmdate('G', $t), 'minute' => (int) gmdate('i', $t)];
    }

    public static function startHour(string $slot): int
    {
        return (int) explode('–', $slot)[0];
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

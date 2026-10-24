<?php
// Настройки сайта, которые меняются в админке: shop (контакты, реквизиты), delivery (зоны, магазины, участки),
// districts (районы), colors (цвета букетов). Не сохранено — берём значения по умолчанию.
final class Settings
{
    private static array $cache = [];

    public static function defaults(string $key): array
    {
        return match ($key) {
            'shop' => Defaults::SHOP,
            'delivery' => Defaults::DELIVERY,
            'districts' => Defaults::DISTRICTS,
            'colors' => Occasions::COLORS,
            default => throw new InvalidArgumentException("Нет настройки $key"),
        };
    }

    public static function get(string $key): array
    {
        if (!array_key_exists($key, self::$cache)) {
            $def = self::defaults($key);
            $raw = Db::value('SELECT value FROM settings WHERE `key` = ?', [$key]);
            $val = $raw === null ? null : json_decode($raw, true);
            // Списки (районы, цвета) храним целиком, объекты дополняем новыми полями по умолчанию
            self::$cache[$key] = !is_array($val) ? $def : (array_is_list($def) ? $val : array_merge($def, $val));
            // Часы салонов, сохранённые ещё как «8:00–21:00» (ежедневно): с 2026-10-24 в воскресенье с 10:00
            if ($key === 'delivery') {
                foreach (self::$cache[$key]['shops'] ?? [] as $i => $s) {
                    if (($s['hours'] ?? '') === '8:00–21:00') self::$cache[$key]['shops'][$i]['hours'] = 'пн–сб 8:00–21:00, вс 10:00–21:00';
                }
            }
            // Реквизиты, сохранённые в админке ещё заготовкой «[Наименование продавца]…», берём настоящие (со старого сайта, 2026-10-24)
            if ($key === 'shop' && str_contains((string) (self::$cache[$key]['sellerDetails'] ?? ''), '[Наименование')) {
                self::$cache[$key]['sellerDetails'] = $def['sellerDetails'];
            }
        }
        return self::$cache[$key];
    }

    public static function save(string $key, array $value): void
    {
        self::defaults($key); // неизвестный ключ — ошибка
        Db::exec('INSERT INTO settings (`key`, value) VALUES (?, ?) ON DUPLICATE KEY UPDATE value = VALUES(value)', [$key, Db::json($value)]);
        self::$cache[$key] = $value;
    }
}

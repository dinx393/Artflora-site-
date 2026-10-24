<?php
// Мелочи для шаблонов витрины: цена «172,50 BYN» с мелким «BYN», склонение «букет/букета/букетов».
final class ShopView
{
    // «пн–сб 8:00…» → «Пн–сб 8:00…»
    public static function ucfirst(string $s): string
    {
        return mb_strtoupper(mb_substr($s, 0, 1)) . mb_substr($s, 1);
    }

    // Гарантия свежести (решение владельца 2026-10-19): в первые сутки что-то случилось с букетом — заменяем.
    public const GUARANTEE_TITLE = 'Гарантия свежести 24 часа';
    public const GUARANTEE_TEXT = 'Цветы — живой организм. Если в первые сутки с букетом что-то случится, напишите нам: мы на связи и поможем с заменой.';
    public const SUBSTITUTION_NOTE = 'Цветы — живой материал. Если нужного цветка или оттенка не окажется, флорист подберёт близкую замену и согласует её с вами до отправки.';

    // Цена: цифры шрифтом заголовков, «BYN» мельче и светлее (решение владельца 2026-10-19). Аналог src/components/Byn.tsx
    public static function byn(int $kop): string
    {
        return e(preg_replace('/ BYN$/', '', Money::format($kop))) . '<small class="cur">BYN</small>';
    }

    // Плашка «Гарантия свежести» (src/components/Guarantee.tsx): на странице букета и compact — в корзине
    public static function guarantee(bool $compact = false): string
    {
        return '<div class="guarantee' . ($compact ? ' compact' : '') . '"><i>' . Icons::get('shield', $compact ? 20 : 24) . '</i>'
            . '<span class="stack" style="gap: 2px"><b>' . e(self::GUARANTEE_TITLE) . '</b><span>' . e(self::GUARANTEE_TEXT) . '</span></span></div>';
    }

    public static function bouquets(int $n): string
    {
        $d = $n % 10;
        $h = $n % 100;
        if ($d === 1 && $h !== 11) return 'букет';
        if ($d >= 2 && $d <= 4 && ($h < 12 || $h > 14)) return 'букета';
        return 'букетов';
    }

    // Активные допы (ваза, шары…) по порядку. $withCard = false — без открытки (она и так бесплатно).
    public static function addons(bool $withCard = true): array
    {
        $rows = Db::all('SELECT id, code, name, priceKop, included FROM addons WHERE active = 1' . ($withCard ? '' : " AND code <> 'card'") . ' ORDER BY sortOrder, id');
        return array_map(fn ($a) => ['id' => (int) $a['id'], 'code' => $a['code'], 'name' => $a['name'], 'priceKop' => (int) $a['priceKop'], 'included' => (bool) $a['included']], $rows);
    }

    // Карта зон доставки (partials/zones-map.php): расчёт зон и сама карта
    // Ссылка на магазин в Яндекс Картах: своя из админки, иначе из Defaults (для старых настроек), иначе поиск по адресу
    public static function mapUrl(array $s): string
    {
        // 2026-10-23 ссылки филиалов сначала были перепутаны и могли сохраниться в админке — такие не берём
        $mixed = ['uruchye' => 'https://yandex.by/maps/-/CXVazMOZ', 'malinovka' => 'https://yandex.by/maps/-/CXVazBM0'];
        if (!empty($s['mapUrl']) && $s['mapUrl'] !== ($mixed[$s['code'] ?? ''] ?? null)) return $s['mapUrl'];
        foreach (Defaults::DELIVERY['shops'] as $d) if ($d['code'] === ($s['code'] ?? '') && $d['address'] === $s['address']) return $d['mapUrl'];
        return 'https://yandex.by/maps/?text=' . rawurlencode('Минск, ' . $s['address']);
    }

    // Адрес магазина ссылкой на карту (открывается в новой вкладке)
    public static function mapLink(array $s, string $text, string $class = ''): string
    {
        return '<a href="' . e(self::mapUrl($s)) . '" target="_blank" rel="noreferrer"' . ($class ? ' class="' . e($class) . '"' : '') . '>' . e($text) . '</a>';
    }

    public static function zonesMapScripts(): string
    {
        return '<script src="' . View::asset('js/delivery-zones.js') . '"></script><script src="' . View::asset('js/delivery-map.js') . '"></script>';
    }
}

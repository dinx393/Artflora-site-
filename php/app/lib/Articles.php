<?php
// Статьи «Советы флориста» и др. (страница /blog). Список — app/content/articles.php, тексты — app/content/articles/*.html.
// Адреса /blog/<slug> те же, что на старом сайте: поиск не теряет позиции.
final class Articles
{
    public const RUBRICS = [
        'sovety' => 'Советы флориста',
        'povod' => 'Цветы к празднику',
        'svadba' => 'Свадьба',
        'salony' => 'Наши салоны',
    ];

    // Статьи старого сайта, которые не переносили (решение владельца 2026-10-24): салона в Грушевке больше нет,
    // букеты из фруктов не делаем. Старые адреса ведём на похожие страницы.
    public const REMOVED = [
        'prodazha-tsvetov-v-grushevke' => '/delivery',
        'bukety-iz-fruktov-i-konfet-zimoj' => '/blog',
        'bukety-iz-osennih-fruktov' => '/blog',
    ];

    private static ?array $all = null;

    // Все статьи, новые сверху
    public static function all(): array
    {
        return self::$all ??= require APP_DIR . '/content/articles.php';
    }

    public static function bySlug(string $slug): ?array
    {
        foreach (self::all() as $a) if ($a['slug'] === $slug) return $a;
        return null;
    }

    public static function byRubric(string $rubric): array
    {
        return array_values(array_filter(self::all(), fn ($a) => $a['rubric'] === $rubric));
    }

    // Текст статьи (HTML из нашего же файла) с телефоном и суммой бесплатной доставки из настроек
    public static function html(array $a): string
    {
        return self::fill((string) file_get_contents(APP_DIR . "/content/articles/{$a['slug']}.html"));
    }

    public static function fill(string $s): string
    {
        $shop = Settings::get('shop');
        return strtr($s, [
            '{{phone}}' => e($shop['phone']),
            '{{freeFrom}}' => e(Money::format(Settings::get('delivery')['freeFromKop'])),
        ]);
    }

    // Другие статьи той же рубрики (соседние по дате), для блока «Ещё статьи»
    public static function related(array $a, int $n = 3): array
    {
        $same = array_values(array_filter(self::byRubric($a['rubric']), fn ($x) => $x['slug'] !== $a['slug']));
        return array_slice($same, 0, $n);
    }

    // «2020-12-14» → «14 декабря 2020»
    public static function date(string $ymd): string
    {
        static $m = ['января', 'февраля', 'марта', 'апреля', 'мая', 'июня', 'июля', 'августа', 'сентября', 'октября', 'ноября', 'декабря'];
        [$y, $mo, $d] = array_map('intval', explode('-', $ymd));
        return "$d {$m[$mo - 1]} $y";
    }
}

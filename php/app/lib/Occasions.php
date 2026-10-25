<?php
// Поводы, форматы, цены и цвета для каталога и админки.
final class Occasions
{
    // Порядок здесь = порядок на сайте: меню «По поводу», «Кому сегодня дарим?» на главной и фильтр «Кому» в каталоге (решение владельца 2026-10-25).
    // Адреса (slug) не меняем: на них завязаны разделы каталога и редиректы.
    public const LIST = [
        ['slug' => 'love', 'label' => 'Любимой', 'title' => 'любимой'],
        ['slug' => 'birthday', 'label' => 'На день рождения', 'title' => 'на день рождения'],
        ['slug' => 'mom', 'label' => 'Маме', 'title' => 'маме'],
        ['slug' => 'colleague', 'label' => 'Коллеге', 'title' => 'коллеге'],
        ['slug' => 'nothing', 'label' => 'Без повода', 'title' => 'без повода'],
        ['slug' => 'wedding', 'label' => 'Для невест', 'title' => 'для невест'],
        ['slug' => 'exotic', 'label' => 'Экзотика', 'title' => 'с экзотикой'],
    ];

    public const FORMATS = [
        'BOUQUET' => ['label' => 'Букет', 'inForm' => 'Букет'],
        'BAG' => ['label' => 'Сумка с цветами', 'inForm' => 'В сумке'],
        'HATBOX' => ['label' => 'Шляпная коробка', 'inForm' => 'Шляпная коробка'],
        'BASKET' => ['label' => 'Корзина', 'inForm' => 'Корзина'],
    ];

    // Разделы над каталогом, которые можно отметить у букета вручную (несколько сразу). «Экзотика» хранится как повод exotic.
    public const SECTIONS = [
        'roses' => 'Розы',
        'exotic' => 'Экзотика',
        'HATBOX' => 'Шляпные коробки',
        'BAG' => 'Сумки с цветами',
        'BASKET' => 'Корзины',
    ];

    public const PRICE_RANGES = [
        ['slug' => 'to100', 'label' => 'До 100 BYN', 'min' => 0, 'max' => 10000],
        ['slug' => '100-150', 'label' => '100–150 BYN', 'min' => 10000, 'max' => 15000],
        ['slug' => 'from150', 'label' => 'От 150 BYN', 'min' => 15000, 'max' => PHP_INT_MAX],
    ];

    // Цвета букетов для фильтра в каталоге. Это список по умолчанию: владелец меняет его
    // и добавляет свои цвета в админке → «Настройки» → «Цвета букетов». swatch — как кружок выглядит на сайте.
    public const MIX_SWATCH = 'conic-gradient(#f2c9d4, #b3122e, #f2c94c, #b9a3d6, #ffffff, #f2c9d4)';
    public const COLORS = [
        ['slug' => 'white', 'label' => 'Белые', 'swatch' => '#ffffff'],
        ['slug' => 'cream', 'label' => 'Кремовые', 'swatch' => '#f3e5c4'],
        ['slug' => 'pink', 'label' => 'Нежно-розовые', 'swatch' => '#f2c9d4'],
        ['slug' => 'raspberry', 'label' => 'Малиновые', 'swatch' => '#c2185b'],
        ['slug' => 'red', 'label' => 'Красные', 'swatch' => '#b3122e'],
        ['slug' => 'burgundy', 'label' => 'Бордовые', 'swatch' => '#7e1f3c'],
        ['slug' => 'peach', 'label' => 'Персиковые', 'swatch' => '#f7b996'],
        ['slug' => 'orange', 'label' => 'Оранжевые', 'swatch' => '#f08a24'],
        ['slug' => 'yellow', 'label' => 'Жёлтые', 'swatch' => '#f2c94c'],
        ['slug' => 'lilac', 'label' => 'Сиреневые', 'swatch' => '#b9a3d6'],
        ['slug' => 'blue', 'label' => 'Синие', 'swatch' => '#3f5fbf'],
        ['slug' => 'green', 'label' => 'Зелёные', 'swatch' => '#6b9a3f'],
        ['slug' => 'mix', 'label' => 'Микс', 'swatch' => self::MIX_SWATCH],
    ];

    public static function label(string $slug): string
    {
        foreach (self::LIST as $o) if ($o['slug'] === $slug) return $o['label'];
        return $slug;
    }

    // В адресе каталога цвета идут через запятую: ?color=pink,red. Можно выбрать несколько.
    public static function parseColors(?string $param, array $list = self::COLORS): array
    {
        $known = array_column($list, 'slug');
        return array_values(array_unique(array_filter(explode(',', $param ?? ''), fn ($c) => in_array($c, $known, true))));
    }

    // Нажали на кружок: был выбран — убираем, не был — добавляем. Пустой список → null (фильтра нет).
    public static function toggleColor(array $selected, string $slug, array $list = self::COLORS): ?string
    {
        $next = in_array($slug, $selected, true) ? array_diff($selected, [$slug]) : [...$selected, $slug];
        $ordered = array_values(array_filter(array_column($list, 'slug'), fn ($c) => in_array($c, $next, true)));
        return $ordered ? implode(',', $ordered) : null;
    }
}

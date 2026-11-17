<?php
// Новогодний сезон (решение владельца 2026-11-12): оформление «Морозная вишня» и акцентный раздел.
// Всё включается и выключается в админке → «Новый год» (настройка season), основной дизайн при этом не меняется.
// Раздел один (bouquets.sections = 'newyear'), а название меняется само по дате (решение 2026-11-12):
// до 25 декабря — «Зимнее настроение», с 25 декабря — «Новый год». Дату смены можно поменять в админке.
final class Season
{
    public const SECTION = 'newyear';
    public const WINTER_SLUG = 'zimnee-nastroenie';
    public const NEWYEAR_SLUG = 'novyj-god';

    // Название и адрес раздела в каждой части сезона. Заголовок и тексты для поиска — в Seo::LANDINGS по этому адресу.
    public const PHASES = [
        'winter' => ['slug' => self::WINTER_SLUG, 'label' => 'Зимнее настроение'],
        'newyear' => ['slug' => self::NEWYEAR_SLUG, 'label' => 'Новый год'],
    ];

    // Включено ли оформление, раздел, снег; дата смены названия (ММ-ДД); часы работы в праздники
    public static function cfg(): array
    {
        return Settings::get('season');
    }

    // Новогоднее оформление (снег, гирлянда, ёлки, иней)
    public static function themeOn(): bool
    {
        return !empty(self::cfg()['theme']);
    }

    public static function snowOn(): bool
    {
        return self::themeOn() && !empty(self::cfg()['snow']);
    }

    public static function garlandOn(): bool
    {
        return self::themeOn() && !empty(self::cfg()['garland']);
    }

    // Раздел на виду: первым в меню, на главной и над каталогом
    public static function sectionOn(): bool
    {
        return !empty(self::cfg()['section']);
    }

    // Когда оформление выключено, сезонный раздел «заморожен»: состав и порядок хранятся,
    // но администраторы не могут случайно изменить список.
    public static function editingOn(): bool
    {
        return self::themeOn();
    }

    public static function orderBouquets(array $bouquets, ?array $order = null): array
    {
        $order ??= (array) (self::cfg()['bouquetOrder'] ?? []);
        $byId = [];
        foreach ($bouquets as $b) $byId[(int) $b['id']] = $b;
        $out = [];
        foreach ($order as $id) {
            $id = (int) $id;
            if (!isset($byId[$id])) continue;
            $out[] = $byId[$id];
            unset($byId[$id]);
        }
        return array_merge($out, array_values($byId));
    }

    public static function protectSections(array $incoming, array $stored, ?bool $editing = null): array
    {
        if ($editing ?? self::editingOn()) return array_values(array_unique($incoming));
        $incoming = array_values(array_diff($incoming, [self::SECTION]));
        if (in_array(self::SECTION, $stored, true)) $incoming[] = self::SECTION;
        return array_values(array_unique($incoming));
    }

    // Сегодня по минскому времени
    public static function today(): DateTimeImmutable
    {
        // ARTFLORA_TODAY=2026-12-26 — посмотреть сайт «как в другой день» на своём компьютере (php/dev)
        $fake = getenv('ARTFLORA_TODAY');
        return new DateTimeImmutable(is_string($fake) && preg_match('/^\d{4}-\d\d-\d\d$/', $fake) ? $fake : 'now', new DateTimeZone('Europe/Minsk'));
    }

    // 'winter' до даты смены (25 декабря), 'newyear' с неё и до конца сезона (январь и позже — тоже «Новый год»).
    // С сентября по дату смены — «Зимнее настроение».
    public static function phase(?DateTimeImmutable $now = null, ?string $switch = null): string
    {
        $now ??= self::today();
        $switch ??= (string) (self::cfg()['switchDate'] ?? '12-25');
        if (!preg_match('/^(\d\d)-(\d\d)$/', $switch)) $switch = '12-25';
        $md = $now->format('m-d');
        if ((int) $now->format('n') < 9) return 'newyear'; // январь–август: праздник уже был
        return $md >= $switch ? 'newyear' : 'winter';
    }

    // Название, адрес и заголовок раздела сейчас
    public static function current(?DateTimeImmutable $now = null): array
    {
        return self::PHASES[self::phase($now)];
    }

    public static function url(): string
    {
        return '/catalog/' . self::current()['slug'];
    }

    // «49 дней до Нового года»; 31 декабря — «Завтра Новый год»; в январе — пусто
    public static function countdown(?DateTimeImmutable $now = null): string
    {
        $now = ($now ?? self::today())->setTime(0, 0);
        if ((int) $now->format('n') < 9) return '';
        $ny = new DateTimeImmutable(((int) $now->format('Y') + 1) . '-01-01', $now->getTimezone());
        $days = (int) $now->diff($ny)->days;
        if ($days === 1) return 'Завтра Новый год';
        return $days . ' ' . self::plural($days, 'день', 'дня', 'дней') . ' до Нового года';
    }

    public static function plural(int $n, string $one, string $few, string $many): string
    {
        $n10 = $n % 10;
        $n100 = $n % 100;
        if ($n10 === 1 && $n100 !== 11) return $one;
        if ($n10 >= 2 && $n10 <= 4 && ($n100 < 12 || $n100 > 14)) return $few;
        return $many;
    }

    // Блок «Успеть к празднику» — с 1 по 31 декабря
    public static function rushOn(?DateTimeImmutable $now = null): bool
    {
        return self::themeOn() && (int) ($now ?? self::today())->format('n') === 12;
    }

    // Ёлочки силуэтом (в первом экране, в вишнёвых блоках, в подвале). $snowy — с белыми шапками снега.
    public static function trees(string $class, string $fill = 'rgba(255,255,255,0.08)', bool $snowy = false): string
    {
        $tree = 'M20 0L31 13H26L35 25H29L40 38H23V46H17V38H0L11 25H5L14 13H9Z';
        $caps = 'M20 0L26 7.5Q20 9 14 7.5Z M14 13Q20 16 26 13L28.5 16.5Q20 19 11.5 16.5Z M11 25Q20 28 29 25L31.5 28.5Q20 31 8.5 28.5Z';
        $at = [[10, 66.4, 1.6], [70, 29.6, 2.4], [140, 57.2, 1.8], [200, 11.2, 2.8], [280, 48.0, 2.0], [340, 71.0, 1.5],
            [390, 20.4, 2.6], [470, 52.6, 1.9], [530, 2.0, 3.0], [610, 38.8, 2.2], [660, 61.8, 1.7]];
        $out = '<svg class="' . e($class) . '" viewBox="0 0 700 140" preserveAspectRatio="xMaxYMax meet" aria-hidden="true">';
        foreach ($at as [$x, $y, $s]) {
            $t = "translate($x $y) scale($s)";
            $out .= '<path transform="' . $t . '" d="' . $tree . '" fill="' . e($fill) . '"/>';
            if ($snowy) $out .= '<path transform="' . $t . '" d="' . $caps . '" fill="#fff" opacity=".9"/>';
        }
        return $out . '</svg>';
    }

    // Гирлянда: провисающие дуги с мигающими лампочками и бантиками (по просьбе dinx 2026-10-25 — классическая)
    public static function garland(): string
    {
        $bulbs = [[27, 28.2, '#C2476B', 1], [59.4, 37.2, '#F2C9D4', 3], [90, 40.0, '#B9A3D6', 5], [120.6, 37.2, '#FFFFFF', 2], [153, 28.2, '#7E1F3C', 4]];
        $arc = '<path d="M0 4Q90 52 180 4" fill="none" stroke="#5C1029" stroke-width="1.6"/><path d="M0 4Q90 40 180 4" fill="none" stroke="#5C1029" stroke-width="0.8" opacity="0.5"/>';
        foreach ($bulbs as [$x, $y, $c, $d]) {
            $stroke = $c === '#FFFFFF' ? ' stroke="#D6CBE3" stroke-width="1"' : '';
            $arc .= '<circle cx="' . $x . '" cy="' . $y . '" r="10" fill="' . $c . '" opacity="0.28" class="ny-tw ny-d' . $d . '"/>'
                . '<rect x="' . ($x - 2.6) . '" y="' . ($y - 13) . '" width="5.2" height="5" rx="1.2" fill="#9C8A94"/>'
                . '<path d="M' . $x . ' ' . ($y - 8) . 'c-5.5 3-5.5 13.5 0 16 5.5-2.5 5.5-13 0-16z" fill="' . $c . '"' . $stroke . ' class="ny-tw ny-d' . $d . '"/>'
                . '<path d="M' . ($x - 1.6) . ' ' . ($y - 3) . 'q-1 3 0 6" fill="none" stroke="#FFFFFF" stroke-width="1.2" stroke-linecap="round" opacity="0.7"/>';
        }
        foreach ([0, 180] as $bx) {
            $arc .= '<path d="M' . $bx . ' 5c-9-7-16-2-11 3 3.5 3 11-3 11-3zM' . $bx . ' 5c9-7 16-2 11 3-3.5 3-11-3-11-3z" fill="#7E1F3C"/>'
                . '<path d="M' . $bx . ' 6l-5 10M' . $bx . ' 6l5 10" stroke="#7E1F3C" stroke-width="2.4" stroke-linecap="round"/>'
                . '<circle cx="' . $bx . '" cy="5" r="3" fill="#5C1029"/>';
        }
        // Гирлянда в границах сайта (как шапка и блоки, просьба dinx 2026-11-13): в ряд встают только целые дуги и чуть растягиваются, чтобы ровно занять ширину от края до края.
        // viewBox шире дуги на 12 с каждой стороны — крайние бантики видны целиком, соседние дуги заходят друг на друга.
        $one = '<svg viewBox="-12 0 204 64" preserveAspectRatio="none">' . $arc . '</svg>';
        return '<div class="wrap"><div class="ny-garland" aria-hidden="true">' . str_repeat($one, 12) . '</div></div>';
    }
}

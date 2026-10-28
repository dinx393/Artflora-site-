<?php
// Букеты из базы для витрины, корзины и админки.
final class Catalog
{
    public const PLACEHOLDER = '/demo/placeholder.svg';
    public const TIMER_HOURS = 36; // таймер «собран сегодня» по умолчанию: полтора суток (владелец, 2026-10-28)
    private static bool $timersChecked = false;

    // Таймер «собран сегодня»: букеты, у которых вышел срок, выключаются (не удаляются) и помечаются «скрыт по таймеру».
    // Вызывает cron раз в 5 минут, а на случай, если cron не настроен, — и любая загрузка букетов (раз за запрос).
    // Если скрытый букет был «Букетом дня», галочка снимается: включённый вручную, он уже не «собран сегодня».
    public static function hideExpired(): int
    {
        self::$timersChecked = true;
        $due = Db::all('SELECT id, slug FROM bouquets WHERE hideAt IS NOT NULL AND hideAt <= NOW()');
        if (!$due) return 0;
        $ids = array_map('intval', array_column($due, 'id'));
        Db::exec('UPDATE bouquets SET hiddenByTimerAt = IF(enabled = 1, hideAt, hiddenByTimerAt), enabled = 0, hideAt = NULL WHERE id ' . Db::in($ids), $ids);
        $shop = Settings::get('shop');
        if (in_array($shop['dayBouquet'], array_column($due, 'slug'), true)) Settings::save('shop', ['dayBouquet' => ''] + $shop);
        return count($due);
    }

    // Сколько осталось до срабатывания таймера: «1 д 4 ч», «5 ч 20 мин», «12 мин» (для админки)
    public static function timerLeft(string $hideAt, ?int $now = null): string
    {
        $min = max(0, intdiv(strtotime($hideAt) - ($now ?? time()) + 59, 60));
        [$d, $h, $m] = [intdiv($min, 1440), intdiv($min % 1440, 60), $min % 60];
        if ($d) return "$d д" . ($h ? " $h ч" : '');
        if ($h) return "$h ч" . ($m ? " $m мин" : '');
        return max(1, $m) . ' мин';
    }

    // «29.10 в 15:30»
    public static function timerDate(string $at): string
    {
        return date('d.m в H:i', strtotime($at));
    }

    // Букеты целиком: с размерами, составом, цветами и их заменами — в форме, которую понимает Pricing.
    // $where — условие на таблицу bouquets (алиас b), например 'b.id IN (?, ?)'.
    public static function load(string $where = '1', array $params = []): array
    {
        $rows = Db::all("SELECT b.*, c.slug AS collectionSlug, c.name AS collectionName
            FROM bouquets b LEFT JOIN collections c ON c.id = b.collectionId
            WHERE $where ORDER BY b.sortOrder, b.id", $params);
        if (!$rows) return [];
        if (!self::$timersChecked && array_filter($rows, fn ($b) => $b['hideAt'] !== null && strtotime($b['hideAt']) <= time())) {
            self::hideExpired();
            return self::load($where, $params);
        }
        $ids = array_column($rows, 'id');
        $sizes = Db::all('SELECT * FROM bouquet_sizes WHERE bouquetId ' . Db::in($ids) . ' ORDER BY id', $ids);
        $sizeIds = array_column($sizes, 'id');
        $items = $sizeIds ? Db::all('SELECT i.sizeId, i.qty, f.id, f.name, f.kind, f.unit, f.priceKop, f.inStock
            FROM bouquet_items i JOIN flowers f ON f.id = i.flowerId
            WHERE i.sizeId ' . Db::in($sizeIds) . ' ORDER BY i.id', $sizeIds) : [];
        $flowerIds = array_values(array_unique(array_column($items, 'id')));
        $subs = [];
        if ($flowerIds) {
            foreach (Db::all('SELECT s.flowerId, f.name, f.inStock FROM flower_substitutes s JOIN flowers f ON f.id = s.substituteId
                WHERE s.flowerId ' . Db::in($flowerIds) . ' ORDER BY f.sortOrder, f.id', $flowerIds) as $s) {
                $subs[$s['flowerId']][] = ['name' => $s['name'], 'inStock' => (bool) $s['inStock']];
            }
        }
        $bySize = [];
        foreach ($items as $i) {
            $bySize[$i['sizeId']][] = [
                'qty' => (float) $i['qty'],
                'flower' => [
                    'id' => (int) $i['id'], 'name' => $i['name'], 'kind' => $i['kind'], 'unit' => $i['unit'],
                    'priceKop' => (int) $i['priceKop'], 'inStock' => (bool) $i['inStock'], 'substitutes' => $subs[$i['id']] ?? [],
                ],
            ];
        }
        $byBouquet = [];
        foreach ($sizes as $s) {
            $byBouquet[$s['bouquetId']][] = [
                'id' => (int) $s['id'], 'code' => $s['code'], 'note' => $s['note'], 'packagingKop' => (int) $s['packagingKop'],
                'items' => $bySize[$s['id']] ?? [],
            ];
        }
        return array_map(fn ($b) => [
            'id' => (int) $b['id'], 'slug' => $b['slug'], 'name' => $b['name'], 'description' => $b['description'],
            'format' => $b['format'], 'enabled' => (bool) $b['enabled'], 'hideAt' => $b['hideAt'], 'hiddenByTimerAt' => $b['hiddenByTimerAt'], 'isHit' => (bool) $b['isHit'], 'isExclusive' => (bool) $b['isExclusive'],
            'weddingOnly' => (bool) $b['weddingOnly'],
            'photoSize' => $b['photoSize'], 'colors' => Db::fromJson($b['colors']), 'occasions' => Db::fromJson($b['occasions']),
            'sections' => Db::fromJson($b['sections']), 'photos' => Db::fromJson($b['photos']), 'sortOrder' => (int) $b['sortOrder'],
            'collectionId' => $b['collectionId'] === null ? null : (int) $b['collectionId'],
            'collectionSlug' => $b['collectionSlug'], 'collectionName' => $b['collectionName'],
            'sizes' => $byBouquet[$b['id']] ?? [],
        ], $rows);
    }

    // Букет для витрины. Невидимые (выключены или не из чего собрать) → null.
    // sizes — только доступные, по порядку XS…XXL: ['code','note','priceKop','composition','flowers'].
    public static function toView(array $b): ?array
    {
        if (!Pricing::visibility($b)['visible']) return null;
        $sizes = array_map(fn ($s) => [
            'code' => $s['code'],
            'note' => $s['note'],
            'priceKop' => Pricing::sizePriceKop($s),
            'composition' => Pricing::compositionText($s['items']),
            'flowers' => Pricing::flowerCount($s['items']),
        ], Pricing::availableSizes($b));
        $def = Pricing::defaultSize($b);
        $names = [];
        foreach (Pricing::sortSizes($b['sizes']) as $s) foreach ($s['items'] as $i) $names[$i['flower']['name']] = true;
        return [
            'id' => $b['id'], 'slug' => $b['slug'], 'name' => $b['name'], 'description' => $b['description'],
            'format' => $b['format'], 'isHit' => $b['isHit'], 'isExclusive' => $b['isExclusive'], 'hideAt' => $b['hideAt'],
            'weddingOnly' => $b['weddingOnly'] && in_array('wedding', $b['occasions'], true),
            'colors' => $b['colors'], 'occasions' => $b['occasions'], 'sections' => $b['sections'],
            'photos' => $b['photos'] ? array_map([Uploads::class, 'url'], $b['photos']) : [self::PLACEHOLDER],
            'collectionId' => $b['collectionId'], 'collectionSlug' => $b['collectionSlug'], 'collectionName' => $b['collectionName'],
            'flowerNames' => array_keys($names),
            'sizes' => $sizes,
            'defaultSize' => $def['code'],
            'priceKop' => Pricing::sizePriceKop($def),
        ];
    }

    // Все видимые букеты для витрины
    public static function visible(string $where = '1', array $params = []): array
    {
        return array_values(array_filter(array_map([self::class, 'toView'], self::load("b.enabled = 1 AND ($where)", $params))));
    }

    public static function bySlug(string $slug): ?array
    {
        $b = self::load('b.slug = ?', [$slug])[0] ?? null;
        return $b ? self::toView($b) : null;
    }

    // Свадебный букет с галочкой «Только в свадебном разделе» виден лишь в разделе «Свадебные букеты» (повод wedding):
    // главная, общий каталог, поиск, другие разделы и «Похожие» его не показывают. Прямая ссылка и корзина работают.
    public static function general(array $list): array
    {
        return array_values(array_filter($list, fn ($b) => empty($b['weddingOnly'])));
    }

    // Фильтр каталога: occasion, price ['min','max'], flower, colors [...] (хотя бы один), format, collection, q (поиск)
    public static function filter(array $list, array $f): array
    {
        $q = mb_strtolower(trim($f['q'] ?? ''));
        if (($f['occasion'] ?? null) !== 'wedding') $list = self::general($list);
        return array_values(array_filter($list, function ($b) use ($f, $q) {
            if (!empty($f['occasion']) && !in_array($f['occasion'], $b['occasions'], true)) return false;
            if (!empty($f['colors']) && !array_intersect($f['colors'], $b['colors'])) return false;
            if (!empty($f['price']) && ($b['priceKop'] < $f['price']['min'] || $b['priceKop'] >= $f['price']['max'])) return false;
            if (!empty($f['flower'])) {
                $needle = mb_strtolower($f['flower']);
                // раздел «Розы» (flower=Роза): ещё и букеты, отмеченные в админке
                $marked = $needle === 'роза' && in_array('roses', $b['sections'] ?? [], true);
                if (!$marked && !array_filter($b['flowerNames'], fn ($n) => str_contains(mb_strtolower($n), $needle))) return false;
            }
            if (!empty($f['format']) && $b['format'] !== $f['format'] && !in_array($f['format'], $b['sections'] ?? [], true)) return false;
            if (!empty($f['collection']) && $b['collectionSlug'] !== $f['collection']) return false;
            if ($q !== '') {
                $hay = mb_strtolower(implode(' ', [$b['name'], $b['description'], ...$b['flowerNames']]));
                // «розы» должно находить «роза»: сравниваем по основе слова
                foreach (preg_split('/\s+/u', $q) as $w) {
                    if (mb_strlen($w) > 4) $w = mb_substr($w, 0, -1);
                    if (!str_contains($hay, $w)) return false;
                }
            }
            return true;
        }));
    }
}

<?php
// «Закупка и списание» (админка → /admin/stock, только старший администратор; решение владельца 2026-11-15).
// Продажи по одной не вносим: пришло по накладным − списали = продали. Деньги в копейках, количество — дробное
// (стебли, штуки, метры ленты).
//   закупили   = сумма строк накладных (с НДС)
//   списали на = количество × цена штуки из последней накладной на дату списания (unitCost)
//   продано на = (пришло − списали) × розничная цена цветка из справочника «Цветы» (только цветы)
//   заработали = продано на − закупка цветов (упаковка — отдельно, её розничной цены нет)
final class Stock
{
    public const KINDS = ['FLOWER' => 'Цветы', 'PACK' => 'Упаковка', 'OTHER' => 'Другое'];
    public const REASONS = [
        'wilted' => 'Завял',
        'broken' => 'Сломан / повреждён',
        'supplier' => 'Брак поставщика',
        'unsold' => 'Не продан букет',
        'other' => 'Другое',
    ];

    // Доходы и расходы по банку (таблица money_entries)
    public const MONEY_IN = [
        'acquiring' => 'Эквайринг (оплата картой)',
        'erip' => 'ЕРИП',
        'cash' => 'Наличные (сдали в банк)',
        'company' => 'Безнал от организаций',
        'flowwow' => 'Flowwow',
        'other_in' => 'Другое',
    ];
    public const MONEY_OUT = [
        'supplier' => 'Поставщики',
        'salary' => 'Зарплата',
        'rent' => 'Аренда',
        'tax' => 'Налоги и взносы',
        'ads' => 'Реклама',
        'delivery' => 'Такси / курьеры',
        'utilities' => 'Коммунальные, связь',
        'bank' => 'Банк (комиссии)',
        'dividends' => 'Дивиденды',
        'corp_card' => 'Корпоративная карточка',
        'loan_sn' => 'Займ СН',
        'other_out' => 'Другое',
    ];

    // Итоги по банку: пришло, ушло, разница, по категориям (больше — выше)
    public static function money(array $entries): array
    {
        $in = $out = 0;
        $byIn = $byOut = [];
        foreach ($entries as $e) {
            $kop = (int) $e['amountKop'];
            if ($e['direction'] === 'IN') {
                $in += $kop;
                $byIn[$e['category']] = ($byIn[$e['category']] ?? 0) + $kop;
            } else {
                $out += $kop;
                $byOut[$e['category']] = ($byOut[$e['category']] ?? 0) + $kop;
            }
        }
        arsort($byIn);
        arsort($byOut);
        return ['inKop' => $in, 'outKop' => $out, 'netKop' => $in - $out, 'byIn' => $byIn, 'byOut' => $byOut];
    }

    // Для сравнения названий: регистр, ё/е, лишние пробелы и кавычки не важны
    public static function norm(string $s): string
    {
        $s = str_replace(['ё', '«', '»', '"', '“', '”'], ['е', '', '', '', '', ''], mb_strtolower($s));
        return mb_substr(trim(preg_replace('/\s+/u', ' ', $s)), 0, 190);
    }

    // Сумму строки делим между магазинами по количеству; копейки от округления — последнему магазину
    public static function splitSum(int $sumKop, array $qtyByShop): array
    {
        $qtyByShop = array_filter($qtyByShop, fn ($q) => $q > 0);
        $total = array_sum($qtyByShop);
        if ($total <= 0) return [];
        $out = [];
        $left = $sumKop;
        $codes = array_keys($qtyByShop);
        foreach ($codes as $i => $code) {
            $part = $i === count($codes) - 1 ? $left : (int) round($sumKop * $qtyByShop[$code] / $total);
            $out[$code] = $part;
            $left -= $part;
        }
        return $out;
    }

    // Цена одной штуки (в копейках, дробная) на дату: из последней накладной не позже этой даты;
    // если раньше накладных не было — из первой после. $history: itemId → [[date, qty, sumKop], …] по дате.
    public static function unitCost(array $history, int $itemId, string $date): ?float
    {
        $rows = $history[$itemId] ?? [];
        if (!$rows) return null;
        $pick = null;
        foreach ($rows as $r) {
            if ($r[0] <= $date) $pick = $r[0];
        }
        $pick ??= $rows[0][0];
        $qty = 0.0;
        $sum = 0;
        foreach ($rows as $r) {
            if ($r[0] === $pick) {
                $qty += (float) $r[1];
                $sum += (int) $r[2];
            }
        }
        return $qty > 0 ? $sum / $qty : null;
    }

    // Розничная цена на дату: последняя запись истории не позже даты; раньше истории — первая; нет истории — $fallback
    public static function retailAt(array $prices, int $id, ?string $date, ?int $fallback): ?int
    {
        $list = $prices[$id] ?? [];
        if (!$list || $date === null) return $fallback;
        $kop = $list[0][1];
        foreach ($list as [$from, $p]) if ($from <= $date) $kop = $p;
        return (int) $kop;
    }

    // История розничных цен по позициям: stockItemId → [[fromDate, kop], …]
    public static function prices(): array
    {
        $out = [];
        foreach (Db::all('SELECT s.id, p.fromDate, p.priceKop FROM flower_prices p JOIN stock_items s ON s.flowerId = p.flowerId ORDER BY s.id, p.fromDate') as $r) {
            $out[(int) $r['id']][] = [(string) $r['fromDate'], (int) $r['priceKop']];
        }
        return $out;
    }

    // Итоги за период.
    //   $lines     — строки накладных: itemId, shop, qty, sumKop
    //   $writeoffs — списания: itemId, shop, qty, woDate, reason
    //   $items     — позиции: id → [name, kind, retailKop|null]
    //   $history   — для unitCost
    //   $shops     — коды магазинов по порядку
    // → items: по позициям (с учётом $shop: null — все магазины), shops: по магазинам, reasons, total
    //   $prices    — история розничных цен: id → [[fromDate, kop], …] по возрастанию даты (необязательно)
    public static function report(array $lines, array $writeoffs, array $items, array $history, array $shops, ?string $shop = null, array $prices = []): array
    {
        $blank = fn () => ['in' => 0.0, 'inKop' => 0, 'wo' => 0.0, 'woKop' => 0, 'noCost' => false, 'inRetail' => 0.0, 'woRetail' => 0.0];
        $retail = fn (int $id, ?string $date) => self::retailAt($prices, $id, $date, $items[$id][2] ?? null);
        $byItem = [];
        $byShop = [];
        foreach ($shops as $code) $byShop[$code] = ['buyFlowersKop' => 0, 'buyOtherKop' => 0, 'woKop' => 0, 'soldKop' => 0, 'items' => []];
        foreach ($lines as $l) {
            $id = (int) $l['itemId'];
            $code = (string) $l['shop'];
            if (!isset($byShop[$code])) continue;
            $byShop[$code]['items'][$id] ??= $blank();
            $byShop[$code]['items'][$id]['in'] += (float) $l['qty'];
            $byShop[$code]['items'][$id]['inKop'] += (int) $l['sumKop'];
            $byShop[$code]['items'][$id]['inRetail'] += (float) $l['qty'] * ($retail($id, $l['docDate'] ?? null) ?? 0);
        }
        $reasons = array_fill_keys(array_keys(self::REASONS), 0);
        foreach ($writeoffs as $w) {
            $id = (int) $w['itemId'];
            $code = (string) $w['shop'];
            if (!isset($byShop[$code])) continue;
            $cost = self::unitCost($history, $id, (string) $w['woDate']);
            $kop = $cost === null ? 0 : (int) round($cost * (float) $w['qty']);
            $byShop[$code]['items'][$id] ??= $blank();
            $byShop[$code]['items'][$id]['wo'] += (float) $w['qty'];
            $byShop[$code]['items'][$id]['woKop'] += $kop;
            $byShop[$code]['items'][$id]['woRetail'] += (float) $w['qty'] * ($retail($id, (string) $w['woDate']) ?? 0);
            if ($cost === null) $byShop[$code]['items'][$id]['noCost'] = true;
            if ($shop === null || $shop === $code) {
                $r = array_key_exists($w['reason'], $reasons) ? $w['reason'] : 'other';
                $reasons[$r] += $kop;
            }
        }
        // Продано и деньги — отдельно по каждому магазину, потом складываем
        foreach ($byShop as $code => &$s) {
            foreach ($s['items'] as $id => &$r) {
                [$name, $kind, $retail] = $items[$id] ?? ['?', 'OTHER', null];
                $r['sold'] = round($r['in'] - $r['wo'], 2);
                // по цене, которая действовала в день накладной / списания
                $r['soldKop'] = $kind === 'FLOWER' && $retail !== null ? (int) round(max(0, $r['inRetail'] - $r['woRetail'])) : 0;
                if ($kind === 'FLOWER') $s['buyFlowersKop'] += $r['inKop'];
                else $s['buyOtherKop'] += $r['inKop'];
                $s['woKop'] += $r['woKop'];
                $s['soldKop'] += $r['soldKop'];
            }
            unset($r);
            $s['buyKop'] = $s['buyFlowersKop'] + $s['buyOtherKop'];
            $s['earnedKop'] = $s['soldKop'] - $s['buyFlowersKop'];
            $s['woPct'] = self::pct($s['woKop'], $s['buyKop']);
        }
        unset($s);

        foreach ($byShop as $code => $s) {
            if ($shop !== null && $shop !== $code) continue;
            foreach ($s['items'] as $id => $r) {
                $byItem[$id] ??= ['id' => $id, 'name' => $items[$id][0] ?? '?', 'kind' => $items[$id][1] ?? 'OTHER', 'retailKop' => $items[$id][2] ?? null] + $blank() + ['sold' => 0.0, 'soldKop' => 0];
                foreach (['in', 'wo', 'sold'] as $k) $byItem[$id][$k] = round($byItem[$id][$k] + $r[$k], 2);
                foreach (['inKop', 'woKop', 'soldKop'] as $k) $byItem[$id][$k] += $r[$k];
                $byItem[$id]['noCost'] = $byItem[$id]['noCost'] || $r['noCost'];
            }
        }
        foreach ($byItem as &$r) $r['woPct'] = self::pct($r['wo'], $r['in']);
        unset($r);
        // Цветы, потом упаковка и прочее; внутри — у кого больше закупка
        $order = array_flip(array_keys(self::KINDS));
        uasort($byItem, fn ($a, $b) => [$order[$a['kind']] ?? 9, -$a['inKop'], -$a['woKop'], $a['name']] <=> [$order[$b['kind']] ?? 9, -$b['inKop'], -$b['woKop'], $b['name']]);

        $total = ['buyFlowersKop' => 0, 'buyOtherKop' => 0, 'buyKop' => 0, 'woKop' => 0, 'soldKop' => 0, 'earnedKop' => 0, 'in' => 0.0, 'wo' => 0.0, 'sold' => 0.0];
        foreach ($byShop as $code => $s) {
            if ($shop !== null && $shop !== $code) continue;
            foreach (['buyFlowersKop', 'buyOtherKop', 'buyKop', 'woKop', 'soldKop', 'earnedKop'] as $k) $total[$k] += $s[$k];
        }
        foreach ($byItem as $r) {
            if ($r['kind'] !== 'FLOWER') continue;
            $total['in'] += $r['in'];
            $total['wo'] += $r['wo'];
            $total['sold'] += $r['sold'];
        }
        $total['woPct'] = self::pct($total['woKop'], $total['buyKop']);
        foreach ($byShop as &$s) unset($s['items']);
        unset($s);
        arsort($reasons);
        return ['items' => array_values($byItem), 'shops' => $byShop, 'reasons' => array_filter($reasons), 'total' => $total];
    }

    // Процент, целое; null — не из чего считать
    public static function pct(float|int $part, float|int $whole): ?int
    {
        return $whole > 0 ? (int) round($part * 100 / $whole) : null;
    }

    // «12», «12,5»
    public static function qty(float|int $q): string
    {
        $s = number_format((float) $q, 2, ',', ' ');
        return rtrim(rtrim($s, '0'), ',');
    }

    // Таблица для Excel: точка с запятой, BOM (иначе Excel путает кодировку), числа с запятой
    public static function csv(array $rows): string
    {
        $out = "\xEF\xBB\xBF";
        foreach ($rows as $row) {
            $out .= implode(';', array_map(function ($v) {
                if (is_int($v) || is_float($v)) return str_replace('.', ',', (string) $v);
                $v = (string) $v;
                if (preg_match('/^[=+\-@]/', $v)) $v = "'" . $v; // чтобы Excel не принял текст за формулу
                return '"' . str_replace('"', '""', $v) . '"';
            }, $row)) . "\r\n";
        }
        return $out;
    }

    // Первый и последний день периода по кнопке: month (этот месяц), prev (прошлый), year, свои даты
    public static function period(string $preset, ?string $from, ?string $to, string $today): array
    {
        $valid = fn (?string $d) => is_string($d) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $d) && strtotime($d) !== false;
        $t = strtotime($today);
        return match (true) {
            $preset === 'custom' && $valid($from) && $valid($to) => $from <= $to ? [$from, $to] : [$to, $from],
            $preset === 'prev' => [date('Y-m-01', strtotime('first day of last month', $t)), date('Y-m-t', strtotime('last day of last month', $t))],
            $preset === 'year' => [date('Y-01-01', $t), date('Y-12-31', $t)],
            $preset === 'week' => [date('Y-m-d', strtotime('monday this week', $t)), date('Y-m-d', strtotime('sunday this week', $t))],
            default => [date('Y-m-01', $t), date('Y-m-t', $t)],
        };
    }

    // ---------- База ----------

    // У каждого цветка из справочника есть позиция для закупки (создаётся сама)
    public static function syncFlowers(): void
    {
        $missing = Db::all('SELECT f.id, f.name FROM flowers f LEFT JOIN stock_items s ON s.flowerId = f.id WHERE s.id IS NULL');
        foreach ($missing as $f) {
            $name = $f['name'];
            if (Db::value('SELECT 1 FROM stock_items WHERE name = ?', [$name])) $name .= ' (цветок)';
            try {
                Db::insert('stock_items', ['name' => mb_substr($name, 0, 120), 'kind' => 'FLOWER', 'flowerId' => (int) $f['id']]);
            } catch (Throwable $e) {
                if (!Db::isDuplicate($e)) throw $e; // одновременно открыли в двух вкладках
            }
        }
    }

    // Все позиции: [{id, name, kind, flowerId, retailKop}] — у цветов название и цена из справочника
    public static function items(): array
    {
        self::syncFlowers();
        $rows = Db::all('SELECT s.id, COALESCE(f.name, s.name) AS name, s.kind, s.flowerId, f.priceKop FROM stock_items s LEFT JOIN flowers f ON f.id = s.flowerId');
        $order = array_flip(array_keys(self::KINDS));
        usort($rows, fn ($a, $b) => [$order[$a['kind']], mb_strtolower($a['name'])] <=> [$order[$b['kind']], mb_strtolower($b['name'])]);
        return array_map(fn ($r) => [
            'id' => (int) $r['id'], 'name' => $r['name'], 'kind' => $r['kind'],
            'flowerId' => $r['flowerId'] === null ? null : (int) $r['flowerId'],
            'retailKop' => $r['priceKop'] === null ? null : (int) $r['priceKop'],
        ], $rows);
    }

    // История закупок для unitCost (все накладные: списание может быть раньше или позже)
    public static function history(): array
    {
        $out = [];
        foreach (Db::all('SELECT l.itemId, p.docDate, SUM(l.qty) AS qty, SUM(l.sumKop) AS sumKop FROM stock_purchase_lines l JOIN stock_purchases p ON p.id = l.purchaseId GROUP BY l.itemId, p.docDate ORDER BY p.docDate') as $r) {
            $out[(int) $r['itemId']][] = [$r['docDate'], (float) $r['qty'], (int) $r['sumKop']];
        }
        return $out;
    }

    public static function shopCodes(): array
    {
        return array_column(Settings::get('delivery')['shops'], 'code');
    }

    // Отчёт за период из базы (+ заказы сайта по магазинам, кроме отменённых)
    public static function load(string $from, string $to, ?string $shop): array
    {
        $items = [];
        foreach (self::items() as $i) $items[$i['id']] = [$i['name'], $i['kind'], $i['retailKop']];
        $lines = Db::all('SELECT l.itemId, l.shop, l.qty, l.sumKop, p.docDate FROM stock_purchase_lines l JOIN stock_purchases p ON p.id = l.purchaseId WHERE p.docDate BETWEEN ? AND ?', [$from, $to]);
        $wo = Db::all('SELECT itemId, shop, qty, woDate, reason FROM stock_writeoffs WHERE woDate BETWEEN ? AND ?', [$from, $to]);
        $history = self::history();
        $r = self::report($lines, $wo, $items, $history, self::shopCodes(), $shop, self::prices());
        foreach (Db::all("SELECT shop, SUM(totalKop) AS kop, COUNT(*) AS n FROM orders WHERE status <> 'CANCELLED' AND deliveryDate BETWEEN ? AND ? GROUP BY shop", [$from, $to]) as $o) {
            if (isset($r['shops'][$o['shop']])) {
                $r['shops'][$o['shop']]['siteKop'] = (int) $o['kop'];
                $r['shops'][$o['shop']]['siteOrders'] = (int) $o['n'];
            }
        }
        $r['total']['siteKop'] = 0;
        foreach ($r['shops'] as $code => $s) if ($shop === null || $shop === $code) $r['total']['siteKop'] += $s['siteKop'] ?? 0;
        return $r;
    }

    // Какой способ оплаты на сайте в какую строку банка попадает (для подсказки «из них с сайта»)
    public const SITE_PAY = ['ERIP' => 'erip', 'CARD' => 'acquiring', 'INVOICE' => 'company'];

    // Оплаченные заказы с сайта за период (по дате доставки, кроме отменённых) → по строкам банка: [категория => [kop, n]]
    public static function sitePaid(string $from, string $to, ?string $shop): array
    {
        $out = [];
        $sql = "SELECT paymentMethod, SUM(totalKop) AS kop, COUNT(*) AS n FROM orders WHERE paid = 1 AND status <> 'CANCELLED' AND deliveryDate BETWEEN ? AND ?" . ($shop ? ' AND shop = ?' : '') . ' GROUP BY paymentMethod';
        foreach (Db::all($sql, $shop ? [$from, $to, $shop] : [$from, $to]) as $r) {
            $cat = self::SITE_PAY[$r['paymentMethod']] ?? null;
            if ($cat === null) continue;
            $out[$cat] ??= [0, 0];
            $out[$cat][0] += (int) $r['kop'];
            $out[$cat][1] += (int) $r['n'];
        }
        return $out;
    }

    // Последние 6 месяцев: закупка и списание по магазинам (для графиков)
    public static function months(string $today, int $n = 6): array
    {
        $first = date('Y-m-01', strtotime('-' . ($n - 1) . ' months', strtotime(date('Y-m-01', strtotime($today)))));
        $last = date('Y-m-t', strtotime($today));
        $codes = self::shopCodes();
        $history = self::history();
        $out = [];
        for ($m = $first; $m <= $last; $m = date('Y-m-01', strtotime('+1 month', strtotime($m)))) {
            $out[substr($m, 0, 7)] = ['buy' => array_fill_keys($codes, 0), 'wo' => array_fill_keys($codes, 0), 'bank' => ['IN' => 0, 'OUT' => 0]];
        }
        foreach (Db::all('SELECT LEFT(p.docDate, 7) AS m, l.shop, SUM(l.sumKop) AS kop FROM stock_purchase_lines l JOIN stock_purchases p ON p.id = l.purchaseId WHERE p.docDate BETWEEN ? AND ? GROUP BY m, l.shop', [$first, $last]) as $r) {
            if (isset($out[$r['m']]['buy'][$r['shop']])) $out[$r['m']]['buy'][$r['shop']] += (int) $r['kop'];
        }
        foreach (Db::all('SELECT LEFT(entryDate, 7) AS m, direction, SUM(amountKop) AS kop FROM money_entries WHERE entryDate BETWEEN ? AND ? GROUP BY m, direction', [$first, $last]) as $r) {
            if (isset($out[$r['m']])) $out[$r['m']]['bank'][$r['direction']] = (int) $r['kop'];
        }
        foreach (Db::all('SELECT itemId, shop, qty, woDate FROM stock_writeoffs WHERE woDate BETWEEN ? AND ?', [$first, $last]) as $w) {
            $m = substr($w['woDate'], 0, 7);
            $cost = self::unitCost($history, (int) $w['itemId'], $w['woDate']);
            if ($cost !== null && isset($out[$m]['wo'][$w['shop']])) $out[$m]['wo'][$w['shop']] += (int) round($cost * (float) $w['qty']);
        }
        return $out;
    }

    // Позиция для строки из накладной/тетради: сначала запомненное название, потом совпадение, потом подсказка распознавания
    public static function match(string $name, array $items, array $aliases, string $hint = ''): ?int
    {
        $n = self::norm($name);
        if ($n !== '' && isset($aliases[$n])) return $aliases[$n];
        foreach ([$n, self::norm($hint)] as $try) {
            if ($try === '') continue;
            foreach ($items as $i) if (self::norm($i['name']) === $try) return $i['id'];
        }
        return null;
    }

    // «Это тот же цветок»: позиция $from целиком переходит в $to (строки накладных, списания, запомненные названия), $from удаляется.
    // Так позиция из накладной связывается с цветком из справочника и получает его розничную цену.
    public static function merge(int $from, int $to): void
    {
        if ($from === $to) return;
        Db::tx(function () use ($from, $to) {
            $name = (string) Db::value('SELECT name FROM stock_items WHERE id = ?', [$from]);
            Db::exec('UPDATE stock_purchase_lines SET itemId = ? WHERE itemId = ?', [$to, $from]);
            Db::exec('UPDATE stock_writeoffs SET itemId = ? WHERE itemId = ?', [$to, $from]);
            Db::exec('UPDATE stock_aliases SET itemId = ? WHERE itemId = ?', [$to, $from]);
            $alias = self::norm($name);
            if ($alias !== '') Db::exec('INSERT INTO stock_aliases (alias, itemId) VALUES (?, ?) ON DUPLICATE KEY UPDATE itemId = VALUES(itemId)', [$alias, $to]);
            Db::exec('DELETE FROM stock_items WHERE id = ?', [$from]);
        });
    }

    public static function aliases(): array
    {
        $out = [];
        foreach (Db::all('SELECT alias, itemId FROM stock_aliases') as $a) $out[$a['alias']] = (int) $a['itemId'];
        return $out;
    }
}

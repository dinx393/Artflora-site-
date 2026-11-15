<?php
// Админка → «Закупка и списание» (/admin/stock): только старший администратор (право stock не входит в Auth::STAFF).
// Отчёт по магазинам и позициям, ввод накладной и списания (вручную или с фото), выгрузка в Excel. Логика — app/lib/Stock.php.

function stock_today(): string
{
    return (string) (getenv('ARTFLORA_TODAY') ?: date('Y-m-d'));
}

function stock_shops(): array
{
    return array_map(fn ($s) => ['code' => $s['code'], 'name' => $s['name'], 'address' => $s['address'] ?? ''], Settings::get('delivery')['shops']);
}

function stock_me(): string
{
    return mb_substr((string) (Auth::user()['name'] ?? ''), 0, 80);
}

function stock_date(mixed $v): ?string
{
    return is_string($v) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $v) && checkdate((int) substr($v, 5, 2), (int) substr($v, 8, 2), (int) substr($v, 0, 4)) ? $v : null;
}

// Период и магазин из адреса страницы
function stock_filter(): array
{
    $preset = is_string($_GET['p'] ?? null) ? $_GET['p'] : 'month';
    [$from, $to] = Stock::period($preset, $_GET['from'] ?? null, $_GET['to'] ?? null, stock_today());
    $shop = is_string($_GET['shop'] ?? null) && in_array($_GET['shop'], Stock::shopCodes(), true) ? $_GET['shop'] : null;
    return [$preset, $from, $to, $shop];
}

Http::get('/admin/stock', function () {
    Auth::requireAdmin('stock');
    [$preset, $from, $to, $shop] = stock_filter();
    $items = [];
    foreach (Stock::items() as $i) $items[$i['id']] = $i;
    $purchases = Db::all('SELECT * FROM stock_purchases WHERE docDate BETWEEN ? AND ? ORDER BY docDate DESC, id DESC', [$from, $to]);
    $ids = array_column($purchases, 'id');
    $plines = $ids ? Db::all('SELECT * FROM stock_purchase_lines WHERE purchaseId ' . Db::in($ids) . ' ORDER BY id', $ids) : [];
    foreach ($purchases as &$p) $p['lines'] = array_values(array_filter($plines, fn ($l) => $l['purchaseId'] === $p['id']));
    unset($p);
    $entries = Db::all('SELECT * FROM money_entries WHERE entryDate BETWEEN ? AND ?' . ($shop ? ' AND shop = ?' : '') . ' ORDER BY entryDate DESC, id DESC LIMIT 500', $shop ? [$from, $to, $shop] : [$from, $to]);
    $writeoffs = Db::all('SELECT * FROM stock_writeoffs WHERE woDate BETWEEN ? AND ?' . ($shop ? ' AND shop = ?' : '') . ' ORDER BY woDate DESC, id DESC LIMIT 300', $shop ? [$from, $to, $shop] : [$from, $to]);
    return admin_page('stock', [
        'title' => 'Финансы',
        'preset' => $preset, 'from' => $from, 'to' => $to, 'shop' => $shop,
        'shops' => stock_shops(),
        'report' => Stock::load($from, $to, $shop),
        'months' => Stock::months(stock_today()),
        'items' => $items,
        'purchases' => $purchases,
        'writeoffs' => $writeoffs,
        'entries' => $entries,
        'bank' => Stock::money($entries),
        'sitePay' => Stock::sitePaid($from, $to, $shop),
        'history' => Stock::history(),
        'msg' => is_string($_GET['msg'] ?? null) ? $_GET['msg'] : null,
    ]);
});

// Excel: по позициям и магазинам за период
Http::get('/admin/stock/export', function () {
    Auth::requireAdmin('stock');
    [, $from, $to, $shop] = stock_filter();
    $shops = stock_shops();
    $rows = [["Финансы: закупка и списание с $from по $to"], ['Магазин', 'Позиция', 'Вид', 'Пришло', 'Списали', 'Продали', '% списания', 'Закупка, BYN', 'Списано на, BYN', 'Продано на, BYN']];
    foreach ($shops as $s) {
        if ($shop && $shop !== $s['code']) continue;
        $r = Stock::load($from, $to, $s['code']);
        foreach ($r['items'] as $i) {
            $rows[] = [$s['name'], $i['name'], Stock::KINDS[$i['kind']] ?? '', $i['in'], $i['wo'], $i['sold'], $i['woPct'] ?? '', $i['inKop'] / 100, $i['woKop'] / 100, $i['soldKop'] / 100];
        }
        $t = $r['shops'][$s['code']];
        $rows[] = [$s['name'], 'ИТОГО', '', '', '', '', $t['woPct'] ?? '', $t['buyKop'] / 100, $t['woKop'] / 100, $t['soldKop'] / 100];
    }
    $rows[] = [];
    $rows[] = ['Списания'];
    $rows[] = ['Дата', 'Магазин', 'Позиция', 'Сколько', 'Причина', 'Кто внёс'];
    $names = [];
    foreach (Stock::items() as $i) $names[$i['id']] = $i['name'];
    $shopName = array_column($shops, 'name', 'code');
    foreach (Db::all('SELECT * FROM stock_writeoffs WHERE woDate BETWEEN ? AND ?' . ($shop ? ' AND shop = ?' : '') . ' ORDER BY woDate, id', $shop ? [$from, $to, $shop] : [$from, $to]) as $w) {
        $rows[] = [$w['woDate'], $shopName[$w['shop']] ?? $w['shop'], $names[$w['itemId']] ?? '?', (float) $w['qty'], Stock::REASONS[$w['reason']] ?? $w['reason'], $w['createdBy']];
    }
    $rows[] = [];
    $rows[] = ['Доходы и расходы по банку'];
    $rows[] = ['Дата', 'Доход / расход', 'Категория', 'Магазин', 'Сумма, BYN', 'Описание', 'Кто внёс'];
    foreach (Db::all('SELECT * FROM money_entries WHERE entryDate BETWEEN ? AND ?' . ($shop ? ' AND shop = ?' : '') . ' ORDER BY entryDate, id', $shop ? [$from, $to, $shop] : [$from, $to]) as $m) {
        $in = $m['direction'] === 'IN';
        $rows[] = [$m['entryDate'], $in ? 'Доход' : 'Расход', ($in ? Stock::MONEY_IN : Stock::MONEY_OUT)[$m['category']] ?? $m['category'], $shopName[$m['shop']] ?? 'Общее', ($in ? 1 : -1) * $m['amountKop'] / 100, $m['description'], $m['createdBy']];
    }
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="zakupka-spisanie-' . $from . '-' . $to . '.csv"');
    header('Cache-Control: no-store');
    echo Stock::csv($rows);
    exit;
});

// Редакторы: накладная и списание (assets/js/admin-stock.js)
function stock_editor(string $mode): string
{
    return admin_page('stock-edit', [
        'title' => $mode === 'purchase' ? 'Новая накладная' : 'Списание',
        'editor' => [
            'mode' => $mode,
            'items' => Stock::items(),
            'kinds' => Stock::KINDS,
            'reasons' => Stock::REASONS,
            'shops' => stock_shops(),
            'today' => stock_today(),
            'recognize' => Recognize::ready(),
            'aliases' => (object) Stock::aliases(),
        ],
        'scripts' => admin_scripts('admin-stock.js'),
    ]);
}

Http::get('/admin/stock/purchase', function () {
    Auth::requireAdmin('stock');
    return stock_editor('purchase');
});

Http::get('/admin/stock/writeoff', function () {
    Auth::requireAdmin('stock');
    return stock_editor('writeoff');
});

// Фото → черновик строк. Ничего не сохраняет.
Http::post('/admin/stock/recognize', function () {
    Auth::requireAdmin('stock');
    Auth::checkCsrf();
    $kind = Http::str('kind', 20);
    if (!in_array($kind, ['invoice', 'split', 'writeoff'], true)) admin_fail('Ошибка');
    $files = $_FILES['files'] ?? null;
    if (!is_array($files) || !is_array($files['tmp_name'] ?? null)) {
        $len = (int) ($_SERVER['CONTENT_LENGTH'] ?? 0);
        admin_fail($len > Uploads::phpLimit() ? 'Фото слишком большие для хостинга — загрузите по одному' : 'Фото не выбраны');
    }
    if (count($files['tmp_name']) > Recognize::MAX_PHOTOS) admin_fail('Не больше ' . Recognize::MAX_PHOTOS . ' фото за раз');
    $photos = [];
    foreach ($files['tmp_name'] as $i => $tmp) {
        if (($files['error'][$i] ?? 1) !== UPLOAD_ERR_OK || !is_uploaded_file($tmp)) admin_fail('Фото не загрузилось, попробуйте ещё раз');
        $bytes = (string) file_get_contents($tmp);
        $info = @getimagesizefromstring($bytes);
        $mime = $info['mime'] ?? '';
        if (!in_array($mime, ['image/jpeg', 'image/png', 'image/webp'], true)) admin_fail('Нужны фото JPG, PNG или WebP');
        if (strlen($bytes) > Recognize::MAX_PHOTO_BYTES) admin_fail('Фото слишком большое');
        $photos[] = [$mime, $bytes];
    }
    if (!$photos) admin_fail('Фото не выбраны');
    $items = Stock::items();
    $names = array_column($items, 'name');
    $shops = array_map(fn ($s) => [$s['code'], $s['name'], $s['address'] ? "адрес {$s['address']}; в тетради могут называть по улице или сокращённо" : ''], stock_shops());
    $r = match ($kind) {
        'invoice' => Recognize::invoice($photos, $names),
        'split' => Recognize::split($photos, $names, $shops),
        'writeoff' => Recognize::writeoff($photos, $names, $shops),
    };
    if (!$r['ok']) Http::json($r);
    $data = $r['data'];
    $aliases = Stock::aliases();
    $lines = [];
    foreach (is_array($data['lines'] ?? null) ? $data['lines'] : [] as $l) {
        if (!is_array($l)) continue;
        $name = mb_substr(trim((string) ($l['name'] ?? '')), 0, 190);
        $l['name'] = $name;
        $l['itemId'] = Stock::match($name, $items, $aliases, (string) ($l['item'] ?? ''));
        $lines[] = $l;
    }
    $data['lines'] = $lines;
    if (isset($data['docDate']) || isset($data['date'])) {
        foreach (['docDate', 'date'] as $k) if (isset($data[$k]) && !stock_date($data[$k])) $data[$k] = '';
    }
    Http::json(['ok' => true, 'data' => $data]);
});

// Позиция строки: существующая или новая (создаётся). Вызывать внутри Db::tx.
function stock_item_id(array $r, array $known): int
{
    $id = $r['itemId'] ?? null;
    if (admin_is_int($id) && isset($known[$id])) return $id;
    $name = is_string($r['newName'] ?? null) ? trim(preg_replace('/\s+/u', ' ', $r['newName'])) : '';
    $kind = $r['newKind'] ?? 'PACK';
    if ($name === '' || mb_strlen($name) > 120 || !array_key_exists($kind, Stock::KINDS)) throw new AdminError('У каждой строки выберите позицию или впишите название новой');
    foreach ($known as $k => $i) if (Stock::norm($i['name']) === Stock::norm($name)) return $k;
    // название раньше связали с другой позицией («Роза 60 см» → цветок из справочника)
    $alias = Db::value('SELECT itemId FROM stock_aliases WHERE alias = ?', [Stock::norm($name)]);
    if ($alias && isset($known[(int) $alias])) return (int) $alias;
    $existing = Db::value('SELECT id FROM stock_items WHERE name = ?', [$name]);
    return $existing ? (int) $existing : Db::insert('stock_items', ['name' => $name, 'kind' => $kind]);
}

function stock_remember(string $name, int $itemId): void
{
    $alias = Stock::norm($name);
    if ($alias !== '') Db::exec('INSERT INTO stock_aliases (alias, itemId) VALUES (?, ?) ON DUPLICATE KEY UPDATE itemId = VALUES(itemId)', [$alias, $itemId]);
}

function stock_qty(mixed $v): ?float
{
    return admin_is_num($v) && $v > 0 && $v <= 100000 ? round((float) $v, 2) : null;
}

Http::post('/admin/stock/purchase/save', function () {
    Auth::requireAdmin('stock');
    Auth::checkCsrf();
    $b = admin_body();
    $date = stock_date($b['docDate'] ?? null) ?? admin_fail('Укажите дату накладной');
    $supplier = admin_str($b['supplier'] ?? '', 160) ?? admin_fail('Слишком длинное название поставщика');
    $number = admin_str($b['docNumber'] ?? '', 60) ?? admin_fail('Слишком длинный номер');
    $comment = admin_str($b['comment'] ?? '', 300) ?? admin_fail('Слишком длинный комментарий');
    $total = $b['docTotalKop'] ?? null;
    if ($total !== null && !admin_is_int($total, 0, 100_000_000)) admin_fail('Проверьте итог накладной');
    $lines = $b['lines'] ?? null;
    $split = $b['split'] ?? null;
    if (!is_array($lines) || !array_is_list($lines) || !$lines || count($lines) > 300 || !is_array($split)) admin_fail('Добавьте хотя бы одну строку');
    $codes = Stock::shopCodes();
    try {
        $id = Db::tx(function () use ($date, $supplier, $number, $comment, $total, $lines, $split, $codes) {
            $known = [];
            foreach (Stock::items() as $i) $known[$i['id']] = $i;
            $agg = []; // itemId → [qty, sumKop, key]
            foreach ($lines as $n => $l) {
                $qty = is_array($l) ? stock_qty($l['qty'] ?? null) : null;
                if ($qty === null || !admin_is_int($l['sumKop'] ?? null, 0, 100_000_000)) throw new AdminError('Строка ' . ($n + 1) . ': проверьте количество и сумму');
                $itemId = stock_item_id($l, $known);
                $known[$itemId] ??= ['id' => $itemId, 'name' => trim((string) $l['newName']), 'kind' => $l['newKind'] ?? 'PACK'];
                $key = is_string($l['key'] ?? null) ? $l['key'] : (string) $itemId;
                $agg[$itemId] ??= [0.0, 0, $key];
                $agg[$itemId][0] = round($agg[$itemId][0] + $qty, 2);
                $agg[$itemId][1] += $l['sumKop'];
                if (is_string($l['name'] ?? null) && trim($l['name']) !== '') stock_remember($l['name'], $itemId);
            }
            $pid = Db::insert('stock_purchases', ['docDate' => $date, 'supplier' => trim($supplier), 'docNumber' => trim($number), 'docTotalKop' => $total, 'comment' => trim($comment), 'createdBy' => stock_me()]);
            foreach ($agg as $itemId => [$qty, $sum, $key]) {
                $byShop = [];
                foreach ($codes as $c) {
                    $q = $split[$key][$c] ?? 0;
                    if (!admin_is_num($q) || $q < 0) throw new AdminError("«{$known[$itemId]['name']}»: проверьте, сколько ушло в магазины");
                    $byShop[$c] = round((float) $q, 2);
                }
                if (abs(array_sum($byShop) - $qty) > 0.001) {
                    throw new AdminError("«{$known[$itemId]['name']}»: пришло " . Stock::qty($qty) . ', а по магазинам расписано ' . Stock::qty(array_sum($byShop)));
                }
                foreach (Stock::splitSum($sum, $byShop) as $c => $part) {
                    Db::insert('stock_purchase_lines', ['purchaseId' => $pid, 'itemId' => $itemId, 'shop' => $c, 'qty' => $byShop[$c], 'sumKop' => $part]);
                }
            }
            return $pid;
        });
    } catch (AdminError $e) {
        admin_fail($e->getMessage());
    } catch (Throwable $e) {
        app_log('stockPurchase: ' . $e->getMessage());
        admin_fail('Не удалось сохранить, ничего не изменилось. Попробуйте ещё раз.');
    }
    Http::json(['ok' => true, 'id' => $id, 'redirect' => '/admin/stock?p=custom&from=' . $date . '&to=' . $date . '&msg=purchase']);
});

Http::post('/admin/stock/writeoff/save', function () {
    Auth::requireAdmin('stock');
    Auth::checkCsrf();
    $b = admin_body();
    $date = stock_date($b['date'] ?? null) ?? admin_fail('Укажите дату списания');
    $rows = $b['rows'] ?? null;
    if (!is_array($rows) || !array_is_list($rows) || !$rows || count($rows) > 300) admin_fail('Добавьте хотя бы одну строку');
    $codes = Stock::shopCodes();
    try {
        Db::tx(function () use ($date, $rows, $codes) {
            $known = [];
            foreach (Stock::items() as $i) $known[$i['id']] = $i;
            foreach ($rows as $n => $r) {
                $qty = is_array($r) ? stock_qty($r['qty'] ?? null) : null;
                if ($qty === null) throw new AdminError('Строка ' . ($n + 1) . ': укажите, сколько списали');
                if (!in_array($r['shop'] ?? '', $codes, true)) throw new AdminError('Строка ' . ($n + 1) . ': выберите магазин');
                $reason = array_key_exists($r['reason'] ?? '', Stock::REASONS) ? $r['reason'] : 'other';
                $itemId = stock_item_id($r, $known);
                $known[$itemId] ??= ['id' => $itemId, 'name' => trim((string) $r['newName']), 'kind' => $r['newKind'] ?? 'FLOWER'];
                $comment = admin_str($r['comment'] ?? '', 300) ?? '';
                Db::insert('stock_writeoffs', ['woDate' => $date, 'shop' => $r['shop'], 'itemId' => $itemId, 'qty' => $qty, 'reason' => $reason, 'comment' => trim($comment), 'createdBy' => stock_me()]);
                if (is_string($r['name'] ?? null) && trim($r['name']) !== '') stock_remember($r['name'], $itemId);
            }
        });
    } catch (AdminError $e) {
        admin_fail($e->getMessage());
    } catch (Throwable $e) {
        app_log('stockWriteoff: ' . $e->getMessage());
        admin_fail('Не удалось сохранить, ничего не изменилось. Попробуйте ещё раз.');
    }
    Http::json(['ok' => true, 'redirect' => '/admin/stock?p=custom&from=' . $date . '&to=' . $date . '&msg=writeoff']);
});

Http::post('/admin/stock/purchase/{id}/delete', function (string $id) {
    Auth::requireAdmin('stock');
    Auth::checkCsrf();
    if (ctype_digit($id)) Db::exec('DELETE FROM stock_purchases WHERE id = ?', [(int) $id]);
    Http::redirect('/admin/stock?' . preg_replace('/[^a-z0-9=&_-]/i', '', Http::str('back', 300)) . '&msg=deleted');
});

Http::post('/admin/stock/writeoff/{id}/delete', function (string $id) {
    Auth::requireAdmin('stock');
    Auth::checkCsrf();
    if (ctype_digit($id)) Db::exec('DELETE FROM stock_writeoffs WHERE id = ?', [(int) $id]);
    Http::redirect('/admin/stock?' . preg_replace('/[^a-z0-9=&_-]/i', '', Http::str('back', 300)) . '&msg=deleted');
});

// ---------- Доходы и расходы по банку ----------

// Куда вернуться после правки: только страницы «Финансов», без старого msg
function stock_back_url(string $url): string
{
    if (!preg_match('~^/admin/stock(/detail)?(\?[\w=&%.\-]*)?$~', $url)) return '/admin/stock';
    return rtrim(preg_replace('/(^|&)msg=[^&]*/', '', $url) ?? '/admin/stock', '?&');
}

Http::get('/admin/stock/money', function () {
    Auth::requireAdmin('stock');
    $dir = ($_GET['dir'] ?? '') === 'out' ? 'OUT' : 'IN';
    // ?id=N — исправить уже внесённую запись
    $entry = ctype_digit((string) ($_GET['id'] ?? '')) ? Db::one('SELECT * FROM money_entries WHERE id = ?', [(int) $_GET['id']]) : null;
    if (isset($_GET['id']) && !$entry) Http::abort(404);
    if ($entry) $dir = $entry['direction'];
    $ref = (string) ($_GET['back'] ?? parse_url((string) ($_SERVER['HTTP_REFERER'] ?? ''), PHP_URL_PATH) . '?' . parse_url((string) ($_SERVER['HTTP_REFERER'] ?? ''), PHP_URL_QUERY));
    return admin_page('stock-money', [
        'title' => $entry ? 'Исправить запись' : ($dir === 'IN' ? 'Доход' : 'Расход'),
        'entry' => $entry,
        'back' => stock_back_url($ref),
        'dir' => $dir,
        'shops' => stock_shops(),
        'date' => stock_date($_GET['date'] ?? null) ?? stock_today(),
        'saved' => isset($_GET['saved']),
        'error' => is_string($_GET['error'] ?? null) ? mb_substr($_GET['error'], 0, 200) : null,
    ]);
});

Http::post('/admin/stock/money/save', function () {
    Auth::requireAdmin('stock');
    Auth::checkCsrf();
    $dir = Http::str('dir') === 'OUT' ? 'OUT' : 'IN';
    $editId = Http::str('id', 12);
    $back = fn (array $q) => Http::redirect('/admin/stock/money?' . http_build_query((ctype_digit($editId) && $editId !== '' ? ['id' => $editId, 'back' => Http::str('back', 400)] : ['dir' => strtolower($dir)]) + $q));
    $date = stock_date(Http::str('date', 10)) ?? $back(['error' => 'Укажите дату']);
    $cats = $dir === 'IN' ? Stock::MONEY_IN : Stock::MONEY_OUT;
    $cat = Http::str('category', 30);
    if (!array_key_exists($cat, $cats)) $back(['date' => $date, 'error' => 'Выберите, что это']);
    $shop = Http::str('shop', 20);
    if ($shop !== '' && !in_array($shop, Stock::shopCodes(), true)) $shop = '';
    $kop = Money::parse(Http::str('amount', 30));
    if ($kop === null || $kop <= 0 || $kop > 100_000_000) $back(['date' => $date, 'error' => 'Проверьте сумму']);
    $desc = trim(Http::str('description', 300));
    $id = Http::str('id', 12);
    if (ctype_digit($id) && $id !== '') {
        Db::exec('UPDATE money_entries SET entryDate = ?, direction = ?, category = ?, shop = ?, amountKop = ?, description = ? WHERE id = ?', [$date, $dir, $cat, $shop, $kop, mb_substr($desc, 0, 300), (int) $id]);
        $to = stock_back_url(Http::str('back', 400));
        Http::redirect($to . (str_contains($to, '?') ? '&' : '?') . 'msg=updated');
    }
    Db::insert('money_entries', ['entryDate' => $date, 'direction' => $dir, 'category' => $cat, 'shop' => $shop, 'amountKop' => $kop, 'description' => mb_substr($desc, 0, 300), 'createdBy' => stock_me()]);
    $back(['date' => $date, 'saved' => 1]);
});

Http::post('/admin/stock/money/{id}/delete', function (string $id) {
    Auth::requireAdmin('stock');
    Auth::checkCsrf();
    if (ctype_digit($id)) Db::exec('DELETE FROM money_entries WHERE id = ?', [(int) $id]);
    $back = Http::str('back', 400);
    if (str_starts_with($back, '/admin/stock')) {
        $to = stock_back_url($back);
        Http::redirect($to . (str_contains($to, '?') ? '&' : '?') . 'msg=deleted');
    }
    Http::redirect('/admin/stock?' . preg_replace('/[^a-z0-9=&_-]/i', '', $back) . '&msg=deleted');
});

// Детализация любой цифры «Финансов»: из чего она сложилась (заказы, строки накладных, списания, записи банка, позиции)
const STOCK_DETAIL = [
    'site' => 'Заказы с сайта',
    'buy' => 'Закупили',
    'wo' => 'Списали',
    'sold' => 'Продали цветов и заработали',
    'bank' => 'Деньги по банку',
    'item' => 'Позиция',
];

Http::get('/admin/stock/detail', function () {
    Auth::requireAdmin('stock');
    [$preset, $from, $to, $shop] = stock_filter();
    $m = is_string($_GET['m'] ?? null) && isset(STOCK_DETAIL[$_GET['m']]) ? $_GET['m'] : Http::abort(404);
    $str = fn (string $k) => is_string($_GET[$k] ?? null) ? $_GET[$k] : '';
    $f = ['kind' => $str('kind'), 'reason' => $str('reason'), 'dir' => $str('dir'), 'cat' => $str('cat'), 'item' => (int) $str('item')];
    $items = [];
    foreach (Stock::items() as $i) $items[$i['id']] = $i;
    $history = Stock::history();
    $shopSql = $shop ? ' AND shop = ?' : '';
    $args = fn (array $a) => $shop ? [...$a, $shop] : $a;
    $data = ['orders' => [], 'lines' => [], 'writeoffs' => [], 'entries' => [], 'report' => null];
    $title = STOCK_DETAIL[$m];

    if ($m === 'site') {
        $data['orders'] = Db::all("SELECT id, number, status, customerName, deliveryMethod, shop, deliveryDate, deliverySlot, paymentMethod, paid, totalKop FROM orders WHERE status <> 'CANCELLED' AND deliveryDate BETWEEN ? AND ?$shopSql ORDER BY deliveryDate, id", $args([$from, $to]));
        // из подсказки «из них с сайта» под строкой банка: только оплаченные этим способом
        if ($f['cat'] !== '') {
            $methods = array_keys(Stock::SITE_PAY, $f['cat'], true);
            $data['orders'] = array_values(array_filter($data['orders'], fn ($o) => $o['paid'] && in_array($o['paymentMethod'], $methods, true)));
        }
        $ids = array_column($data['orders'], 'id');
        $what = [];
        if ($ids) foreach (Db::all('SELECT orderId, name, size, qty FROM order_items WHERE orderId ' . Db::in($ids) . ' ORDER BY id', $ids) as $it) {
            $what[$it['orderId']][] = $it['name'] . ($it['size'] ? ' ' . $it['size'] : '') . ((int) $it['qty'] > 1 ? ' × ' . $it['qty'] : '');
        }
        foreach ($data['orders'] as &$o) $o['what'] = implode(', ', $what[$o['id']] ?? []);
        unset($o);
    }
    if ($m === 'buy' || $m === 'item') {
        $sql = 'SELECT l.*, p.docDate, p.supplier, p.docNumber FROM stock_purchase_lines l JOIN stock_purchases p ON p.id = l.purchaseId WHERE p.docDate BETWEEN ? AND ?' . ($shop ? ' AND l.shop = ?' : '');
        $rows = Db::all($sql . ' ORDER BY p.docDate, p.id, l.id', $args([$from, $to]));
        if ($f['kind'] === 'flowers') $rows = array_filter($rows, fn ($l) => ($items[$l['itemId']]['kind'] ?? '') === 'FLOWER');
        if ($f['kind'] === 'other') $rows = array_filter($rows, fn ($l) => ($items[$l['itemId']]['kind'] ?? '') !== 'FLOWER');
        if ($m === 'item') $rows = array_filter($rows, fn ($l) => (int) $l['itemId'] === $f['item']);
        $data['lines'] = array_values($rows);
    }
    if ($m === 'wo' || $m === 'item') {
        $rows = Db::all("SELECT * FROM stock_writeoffs WHERE woDate BETWEEN ? AND ?$shopSql ORDER BY woDate, id", $args([$from, $to]));
        if ($f['reason'] !== '') $rows = array_filter($rows, fn ($w) => $w['reason'] === $f['reason'] || ($f['reason'] === 'other' && !isset(Stock::REASONS[$w['reason']])));
        if ($m === 'item') $rows = array_filter($rows, fn ($w) => (int) $w['itemId'] === $f['item']);
        foreach ($rows as &$w) {
            $c = Stock::unitCost($history, (int) $w['itemId'], $w['woDate']);
            $w['kop'] = $c === null ? null : (int) round($c * (float) $w['qty']);
        }
        unset($w);
        $data['writeoffs'] = array_values($rows);
    }
    if ($m === 'sold') $data['report'] = Stock::load($from, $to, $shop);
    if ($m === 'bank') {
        $rows = Db::all("SELECT * FROM money_entries WHERE entryDate BETWEEN ? AND ?$shopSql ORDER BY entryDate, id", $args([$from, $to]));
        if (in_array($f['dir'], ['IN', 'OUT'], true)) $rows = array_filter($rows, fn ($e) => $e['direction'] === $f['dir']);
        $isOwner = fn ($e) => $e['direction'] === 'OUT' && in_array($e['category'], Stock::OWNER, true);
        if ($f['dir'] === 'OWNER') $rows = array_filter($rows, $isOwner);
        if ($f['dir'] === 'BIZ') $rows = array_filter($rows, fn ($e) => !$isOwner($e));
        if ($f['dir'] === 'BIZOUT') $rows = array_filter($rows, fn ($e) => $e['direction'] === 'OUT' && !$isOwner($e));
        if ($f['cat'] !== '') $rows = array_filter($rows, fn ($e) => $e['category'] === $f['cat']);
        $data['entries'] = array_values($rows);
    }
    if ($m === 'item') $title = $items[$f['item']]['name'] ?? Http::abort(404);

    return admin_page('stock-detail', [
        'title' => $title . ' — Финансы',
        'heading' => $title,
        'm' => $m, 'f' => $f,
        'preset' => $preset, 'from' => $from, 'to' => $to, 'shop' => $shop,
        'shops' => stock_shops(), 'items' => $items,
    ] + $data);
});

// «Это тот же цветок, что в справочнике»: позиция из накладной сливается с цветком и получает его розничную цену
Http::post('/admin/stock/item/{id}/merge', function (string $id) {
    Auth::requireAdmin('stock');
    Auth::checkCsrf();
    $items = [];
    foreach (Stock::items() as $i) $items[$i['id']] = $i;
    $from = (int) $id;
    $to = (int) Http::str('to');
    if (!isset($items[$from], $items[$to]) || $from === $to) Http::abort(404);
    if ($items[$from]['flowerId'] !== null) Http::abort(403); // цветок из справочника не сливаем, только в него
    Stock::merge($from, $to);
    $back = Http::str('back');
    Http::redirect('/admin/stock/detail?' . http_build_query(['m' => 'item', 'item' => $to]) . (preg_match('/^[\w=&%.-]*$/', $back) && $back !== '' ? '&' . $back : '') . '&merged=1');
});

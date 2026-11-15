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

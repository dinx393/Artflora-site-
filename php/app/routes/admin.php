<?php
// Админка: букеты, цветы, коллекции, заказы, доставка, настройки. Повторяет src/app/admin (версия на Node.js).
// Каждый обработчик начинается с Auth::requireAdmin(); POST — ещё и с Auth::checkCsrf().
// Редакторы (цветы, букет, доставка, настройки) работают в браузере (assets/js/admin-*.js)
// и сохраняют JSON-запросом; простые действия — обычными формами с переходом обратно.

// Ошибка с понятным текстом для админки
final class AdminError extends RuntimeException {}

const ADMIN_STATUS_LABEL = [
    'NEW' => 'Новый',
    'CONFIRMED' => 'Собираем',
    'PHOTO_SENT' => 'Фото отправлено',
    'ON_THE_WAY' => 'В пути / ждёт',
    'DONE' => 'Выполнен',
    'CANCELLED' => 'Отменён',
];
const ADMIN_PAY_LABEL = ['CARD' => 'Картой (ссылкой)', 'ERIP' => 'ЕРИП', 'VIBER_LINK' => 'Ссылкой в Viber'];

function admin_page(string $template, array $data): string
{
    return View::page("admin/$template", $data, 'admin');
}

// Данные для скрипта страницы: <script type="application/json">…</script>
function admin_json(mixed $v): string
{
    return json_encode($v, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP);
}

// Скрипты редактора: общие помощники + свой файл
function admin_scripts(string ...$files): string
{
    $out = '';
    foreach (['admin-ui.js', ...$files] as $f) $out .= '<script src="' . View::asset("js/$f") . '"></script>';
    return $out;
}

function admin_fail(string $error): never
{
    Http::json(['ok' => false, 'error' => $error]);
}

// Тело JSON-запроса из редактора; не то — ошибка
function admin_body(): array
{
    $b = Http::jsonBody();
    if (!is_array($b)) admin_fail('Страница устарела. Обновите её (F5) и попробуйте ещё раз');
    return $b;
}

function admin_norm(string $t): string
{
    return trim(str_replace('ё', 'е', mb_strtolower($t)));
}

function admin_is_int(mixed $v, int $min = PHP_INT_MIN, int $max = PHP_INT_MAX): bool
{
    return is_int($v) && $v >= $min && $v <= $max;
}

function admin_is_num(mixed $v): bool
{
    return (is_int($v) || is_float($v)) && is_finite((float) $v);
}

function admin_str(mixed $v, int $max): ?string
{
    return is_string($v) && mb_strlen($v) <= $max ? $v : null;
}

// «Букет «Rose»» → buket-rose
function admin_slugify(string $name): string
{
    $map = [
        'а' => 'a', 'б' => 'b', 'в' => 'v', 'г' => 'g', 'д' => 'd', 'е' => 'e', 'ё' => 'e', 'ж' => 'zh', 'з' => 'z', 'и' => 'i', 'й' => 'y',
        'к' => 'k', 'л' => 'l', 'м' => 'm', 'н' => 'n', 'о' => 'o', 'п' => 'p', 'р' => 'r', 'с' => 's', 'т' => 't', 'у' => 'u', 'ф' => 'f',
        'х' => 'h', 'ц' => 'c', 'ч' => 'ch', 'ш' => 'sh', 'щ' => 'sch', 'ъ' => '', 'ы' => 'y', 'ь' => '', 'э' => 'e', 'ю' => 'yu', 'я' => 'ya',
        'і' => 'i', 'ў' => 'u',
    ];
    $s = strtr(mb_strtolower($name), $map);
    $s = trim(preg_replace('/[^a-z0-9]+/', '-', $s), '-');
    return substr($s, 0, 60) ?: 'buket';
}

function admin_free_slug(string $table, string $base): string
{
    $slug = $base;
    for ($n = 2; Db::value("SELECT 1 FROM `$table` WHERE slug = ?", [$slug]); $n++) $slug = "$base-$n";
    return $slug;
}

// «23 окт.», «пятница, 23 октября» — без расширения intl, его может не быть на хостинге
function admin_date(string $ymd, bool $long = false): string
{
    $t = strtotime($ymd);
    $day = (int) date('j', $t);
    $m = (int) date('n', $t) - 1;
    if (!$long) {
        $short = ['янв.', 'февр.', 'мар.', 'апр.', 'мая', 'июн.', 'июл.', 'авг.', 'сент.', 'окт.', 'нояб.', 'дек.'];
        return "$day {$short[$m]}";
    }
    $months = ['января', 'февраля', 'марта', 'апреля', 'мая', 'июня', 'июля', 'августа', 'сентября', 'октября', 'ноября', 'декабря'];
    $days = ['воскресенье', 'понедельник', 'вторник', 'среда', 'четверг', 'пятница', 'суббота'];
    return $days[(int) date('w', $t)] . ", $day {$months[$m]}";
}

function admin_byn(int $kop): string
{
    return e(preg_replace('/ BYN$/', '', Money::format($kop))) . '<small class="cur">BYN</small>';
}

function admin_smtp_ready(): bool
{
    if (class_exists('Notify') && method_exists('Notify', 'smtpConfigured')) return Notify::smtpConfigured();
    return Config::get('smtp_host') && Config::get('smtp_user') && Config::get('smtp_pass');
}

function admin_telegram_ready(): bool
{
    if (class_exists('Notify') && method_exists('Notify', 'telegramConfigured')) return Notify::telegramConfigured();
    return Config::get('telegram_bot_token') && Config::get('telegram_chat_id');
}

// ---------- Букеты ----------

Http::get('/admin/bouquets', function () {
    Auth::requireAdmin();
    $tab = in_array($_GET['tab'] ?? '', ['all', 'on', 'off', 'missing'], true) ? $_GET['tab'] : 'all';
    $list = array_map(function ($b) {
        $vis = Pricing::visibility($b);
        $main = Pricing::defaultSize($b);
        if (!$main) {
            foreach (Pricing::sortSizes($b['sizes']) as $s) if ($s['code'] === 'M') $main = $s;
            $main ??= $b['sizes'][0] ?? null;
        }
        return ['b' => $b, 'vis' => $vis, 'main' => $main, 'price' => $main ? Pricing::sizePriceKop($main) : 0];
    }, Catalog::load());
    $is = [
        'all' => fn ($x) => true,
        'on' => fn ($x) => $x['vis']['visible'],
        'off' => fn ($x) => !$x['vis']['visible'] && $x['vis']['reason'] === 'disabled',
        'missing' => fn ($x) => !$x['vis']['visible'] && $x['vis']['reason'] === 'no-flowers',
    ];
    $counts = array_map(fn ($f) => count(array_filter($list, $f)), $is);
    return admin_page('bouquets', [
        'title' => 'Букеты',
        'tab' => $tab,
        'counts' => $counts,
        'shown' => array_values(array_filter($list, $is[$tab])),
        'scripts' => '<script src="' . View::asset('js/admin-bouquets.js') . '"></script>',
    ]);
});

// Переключатель «Показывать» в списке
Http::post('/admin/bouquets/toggle', function () {
    Auth::requireAdmin();
    Auth::checkCsrf();
    $b = admin_body();
    if (!admin_is_int($b['id'] ?? null) || !is_bool($b['enabled'] ?? null)) admin_fail('Ошибка');
    Db::exec('UPDATE bouquets SET enabled = ? WHERE id = ?', [(int) $b['enabled'], $b['id']]);
    Http::json(['ok' => true]);
});

Http::post('/admin/bouquets/new', function () {
    Auth::requireAdmin();
    Auth::checkCsrf();
    $id = Db::insert('bouquets', [
        'name' => 'Новый букет', 'slug' => admin_free_slug('bouquets', 'novyy-buket'), 'description' => '',
        'enabled' => 0, 'packagingKop' => 800, 'colors' => '[]', 'occasions' => '[]', 'photos' => '[]',
    ]);
    Http::redirect("/admin/bouquets/$id");
});

Http::get('/admin/bouquets/{id}', function (string $id) {
    Auth::requireAdmin();
    if (!ctype_digit($id)) Http::abort(404);
    $b = Db::one('SELECT * FROM bouquets WHERE id = ?', [(int) $id]) ?? Http::abort(404);
    $sizes = Db::all('SELECT * FROM bouquet_sizes WHERE bouquetId = ?', [$b['id']]);
    $items = $sizes ? Db::all('SELECT sizeId, flowerId, qty FROM bouquet_items WHERE sizeId ' . Db::in(array_column($sizes, 'id')) . ' ORDER BY id', array_column($sizes, 'id')) : [];
    $initial = [
        'id' => (int) $b['id'], 'name' => $b['name'], 'slug' => $b['slug'], 'description' => $b['description'], 'format' => $b['format'],
        'enabled' => (bool) $b['enabled'], 'isHit' => (bool) $b['isHit'], 'isExclusive' => (bool) $b['isExclusive'], 'photoSize' => $b['photoSize'],
        'colors' => Db::fromJson($b['colors']), 'occasions' => Db::fromJson($b['occasions']), 'photos' => Db::fromJson($b['photos']),
        'collectionId' => $b['collectionId'] === null ? null : (int) $b['collectionId'],
        'sizes' => array_map(function ($code) use ($sizes, $items, $b) {
            $s = null;
            foreach ($sizes as $x) if ($x['code'] === $code) $s = $x;
            $its = $s ? array_values(array_filter($items, fn ($i) => $i['sizeId'] === $s['id'])) : [];
            return [
                'code' => $code, 'note' => $s['note'] ?? '', 'packagingKop' => (int) ($s['packagingKop'] ?? $b['packagingKop']),
                'items' => array_map(fn ($i) => ['flowerId' => (int) $i['flowerId'], 'qty' => (float) $i['qty']], $its),
            ];
        }, Pricing::SIZE_ORDER),
    ];
    $subs = [];
    foreach (Db::all('SELECT flowerId, substituteId FROM flower_substitutes ORDER BY substituteId') as $s) $subs[$s['flowerId']][] = (int) $s['substituteId'];
    $flowers = array_map(fn ($f) => [
        'id' => (int) $f['id'], 'name' => $f['name'], 'kind' => $f['kind'], 'unit' => $f['unit'], 'priceKop' => (int) $f['priceKop'],
        'inStock' => (bool) $f['inStock'], 'substitutes' => $subs[$f['id']] ?? [],
    ], Db::all('SELECT * FROM flowers ORDER BY kind, sortOrder, name'));
    $collections = array_map(fn ($c) => ['id' => (int) $c['id'], 'name' => $c['name']], Db::all('SELECT id, name FROM collections ORDER BY name'));
    $siblings = $b['collectionId'] === null ? [] : array_map(
        fn ($s) => ['id' => (int) $s['id'], 'name' => $s['name'], 'format' => $s['format']],
        Db::all('SELECT id, name, format FROM bouquets WHERE collectionId = ? ORDER BY id', [$b['collectionId']]),
    );
    return admin_page('bouquet', [
        'title' => $b['name'],
        'editor' => [
            'initial' => $initial, 'flowers' => $flowers, 'collections' => $collections, 'siblings' => $siblings,
            'colors' => Settings::get('colors'), 'occasions' => Occasions::LIST, 'formats' => Occasions::FORMATS,
        ],
        'scripts' => admin_scripts('admin-bouquet.js'),
    ]);
});

Http::post('/admin/bouquets/save', function () {
    Auth::requireAdmin();
    Auth::checkCsrf();
    $in = admin_body();
    $bad = 'Проверьте поля букета';
    $name = is_string($in['name'] ?? null) ? trim($in['name']) : '';
    if ($name === '') admin_fail('Введите название');
    if (mb_strlen($name) > 120) admin_fail($bad);
    $slug = is_string($in['slug'] ?? null) ? trim($in['slug']) : '';
    if (!preg_match('/^[a-z0-9-]+$/', $slug)) admin_fail('Адрес страницы: только латиница, цифры и дефис');
    if (strlen($slug) > 100) admin_fail($bad);
    $id = $in['id'] ?? null;
    $photoSize = $in['photoSize'] ?? 'M';
    $lists = ['colors' => [10, 20], 'occasions' => [10, 30], 'photos' => [12, 300]];
    if (!admin_is_int($id) || admin_str($in['description'] ?? null, 2000) === null
        || !array_key_exists($in['format'] ?? '', Occasions::FORMATS) || !in_array($photoSize, Pricing::SIZE_ORDER, true)
        || !is_bool($in['enabled'] ?? null) || !is_bool($in['isHit'] ?? null) || !is_bool($in['isExclusive'] ?? null)
        || !(($in['collectionId'] ?? null) === null || admin_is_int($in['collectionId']))
        || !is_array($in['sizes'] ?? null) || count($in['sizes']) > count(Pricing::SIZE_ORDER)) admin_fail($bad);
    foreach ($lists as $k => [$max, $len]) {
        $l = $in[$k] ?? null;
        if (!is_array($l) || !array_is_list($l) || count($l) > $max) admin_fail($bad);
        foreach ($l as $v) if (admin_str($v, $len) === null) admin_fail($bad);
    }
    $sizes = [];
    foreach ($in['sizes'] as $s) {
        if (!is_array($s) || !in_array($s['code'] ?? '', Pricing::SIZE_ORDER, true) || admin_str($s['note'] ?? null, 120) === null
            || !admin_is_int($s['packagingKop'] ?? null, 0, 1_000_000) || !is_array($s['items'] ?? null) || count($s['items']) > 40) admin_fail($bad);
        // одинаковые цветы в одном размере складываем
        $merged = [];
        foreach ($s['items'] as $i) {
            $q = $i['qty'] ?? null;
            if (!admin_is_int($i['flowerId'] ?? null) || !admin_is_num($q) || $q <= 0 || $q > 500) admin_fail($bad);
            $merged[$i['flowerId']] = ($merged[$i['flowerId']] ?? 0) + round($q * 100) / 100;
        }
        if ($merged) $sizes[] = ['code' => $s['code'], 'note' => $s['note'], 'packagingKop' => $s['packagingKop'], 'items' => $merged];
    }
    if (!$sizes) admin_fail('Добавьте цветы хотя бы в один размер');
    if (Db::value('SELECT 1 FROM bouquets WHERE slug = ? AND id <> ?', [$slug, $id])) admin_fail('Такой адрес страницы уже занят другим букетом');
    // упаковка размера «на фото» — её же подставляем новым размерам
    $photo = $sizes[0];
    foreach ($sizes as $s) if ($s['code'] === $photoSize) $photo = $s;
    try {
        Db::tx(function () use ($in, $id, $name, $slug, $photoSize, $photo, $sizes) {
            Db::update('bouquets', $id, [
                'name' => $name, 'slug' => $slug, 'description' => $in['description'], 'format' => $in['format'],
                'packagingKop' => $photo['packagingKop'], 'enabled' => (int) $in['enabled'], 'isHit' => (int) $in['isHit'],
                'isExclusive' => (int) $in['isExclusive'], 'photoSize' => $photoSize, 'colors' => Db::json($in['colors']),
                'occasions' => Db::json($in['occasions']), 'photos' => Db::json($in['photos']), 'collectionId' => $in['collectionId'],
            ]);
            Db::exec('DELETE FROM bouquet_sizes WHERE bouquetId = ?', [$id]);
            foreach ($sizes as $s) {
                $sizeId = Db::insert('bouquet_sizes', ['bouquetId' => $id, 'code' => $s['code'], 'note' => $s['note'], 'packagingKop' => $s['packagingKop']]);
                foreach ($s['items'] as $flowerId => $qty) Db::insert('bouquet_items', ['sizeId' => $sizeId, 'flowerId' => $flowerId, 'qty' => $qty]);
            }
        });
    } catch (Throwable $e) {
        app_log('saveBouquet: ' . $e->getMessage());
        admin_fail('Не удалось сохранить букет, ничего не изменилось. Обновите страницу (F5) и попробуйте ещё раз.');
    }
    Http::json(['ok' => true]);
});

// «+ Создать с этим составом»: копия букета в другом формате, в той же коллекции.
Http::post('/admin/bouquets/{id}/clone', function (string $id) {
    Auth::requireAdmin();
    Auth::checkCsrf();
    $format = (string) ($_POST['format'] ?? '');
    $src = Db::one('SELECT * FROM bouquets WHERE id = ?', [(int) $id]);
    if (!$src || !array_key_exists($format, Occasions::FORMATS)) Http::abort(404);
    $copyId = Db::tx(function () use ($src, $format) {
        $collectionId = $src['collectionId'];
        if ($collectionId === null) {
            $cname = preg_replace('/^(Букет|Сумка с цветами)\s*/iu', '', $src['name']) ?: $src['name'];
            $collectionId = Db::insert('collections', ['slug' => admin_free_slug('collections', admin_slugify($src['name'])), 'name' => $cname]);
            Db::update('bouquets', (int) $src['id'], ['collectionId' => $collectionId]);
        }
        $collection = Db::value('SELECT name FROM collections WHERE id = ?', [$collectionId]);
        $name = Occasions::FORMATS[$format]['label'] . ' «' . str_replace(['«', '»'], '', $collection) . '»';
        $copyId = Db::insert('bouquets', [
            'name' => $name, 'slug' => admin_free_slug('bouquets', admin_slugify($name)), 'description' => $src['description'],
            'format' => $format, 'packagingKop' => $src['packagingKop'],
            'enabled' => 0, // включите, когда добавите фото
            'colors' => '[]', 'occasions' => $src['occasions'], 'photos' => '[]', 'collectionId' => $collectionId,
        ]);
        foreach (Db::all('SELECT * FROM bouquet_sizes WHERE bouquetId = ?', [$src['id']]) as $s) {
            $sizeId = Db::insert('bouquet_sizes', ['bouquetId' => $copyId, 'code' => $s['code'], 'note' => $s['note'], 'packagingKop' => $s['packagingKop']]);
            Db::exec('INSERT INTO bouquet_items (sizeId, flowerId, qty) SELECT ?, flowerId, qty FROM bouquet_items WHERE sizeId = ? ORDER BY id', [$sizeId, $s['id']]);
        }
        return $copyId;
    });
    Http::redirect("/admin/bouquets/$copyId");
});

Http::post('/admin/bouquets/{id}/delete', function (string $id) {
    Auth::requireAdmin();
    Auth::checkCsrf();
    Db::exec('DELETE FROM bouquets WHERE id = ?', [(int) $id]); // заказы хранят копию названия и цены
    Http::redirect('/admin/bouquets');
});

Http::post('/admin/upload', function () {
    Auth::requireAdmin();
    Auth::checkCsrf();
    if (!isset($_FILES['file'])) admin_fail('Файл не выбран');
    Http::json(Uploads::save($_FILES['file']));
});

// ---------- Цветы ----------

// Справочник в порядке, который задаёт владелец перетаскиванием
function admin_flowers(): array
{
    $subs = [];
    foreach (Db::all('SELECT flowerId, substituteId FROM flower_substitutes ORDER BY substituteId') as $s) $subs[$s['flowerId']][] = (int) $s['substituteId'];
    return array_map(fn ($f) => [
        'id' => (int) $f['id'], 'name' => $f['name'], 'kind' => $f['kind'], 'unit' => $f['unit'], 'priceKop' => (int) $f['priceKop'],
        'prevPriceKop' => $f['prevPriceKop'] === null ? null : (int) $f['prevPriceKop'], 'inStock' => (bool) $f['inStock'],
        'substitutes' => $subs[$f['id']] ?? [],
    ], Db::all('SELECT * FROM flowers ORDER BY sortOrder, name'));
}

Http::get('/admin/flowers', function () {
    Auth::requireAdmin();
    $sizes = Db::all('SELECT s.id, s.bouquetId, s.code, s.packagingKop FROM bouquet_sizes s ORDER BY s.id');
    $items = Db::all('SELECT sizeId, flowerId, qty FROM bouquet_items ORDER BY id');
    $bouquets = array_map(fn ($b) => [
        'id' => (int) $b['id'], 'name' => $b['name'], 'enabled' => (bool) $b['enabled'],
        'sizes' => array_values(array_map(fn ($s) => [
            'code' => $s['code'], 'packagingKop' => (int) $s['packagingKop'],
            'items' => array_values(array_map(
                fn ($i) => ['flowerId' => (int) $i['flowerId'], 'qty' => (float) $i['qty']],
                array_filter($items, fn ($i) => $i['sizeId'] === $s['id']),
            )),
        ], array_filter($sizes, fn ($s) => $s['bouquetId'] === $b['id']))),
    ], Db::all('SELECT id, name, enabled FROM bouquets ORDER BY id'));
    return admin_page('flowers', [
        'title' => 'Цветы',
        'editor' => ['initial' => admin_flowers(), 'bouquets' => $bouquets],
        'scripts' => admin_scripts('admin-flowers.js'),
    ]);
});

Http::post('/admin/flowers/save', function () {
    Auth::requireAdmin();
    Auth::checkCsrf();
    $rows = admin_body();
    $bad = 'Проверьте названия и цены';
    if (!array_is_list($rows) || count($rows) > 500) admin_fail($bad);
    $clean = [];
    foreach ($rows as $r) {
        $name = is_array($r) && is_string($r['name'] ?? null) ? trim($r['name']) : '';
        $subs = $r['substitutes'] ?? [];
        if ($name === '' || mb_strlen($name) > 80 || !in_array($r['kind'] ?? '', ['FLOWER', 'GREEN'], true)
            || !in_array($r['unit'] ?? '', ['STEM', 'PIECE'], true) || !admin_is_int($r['priceKop'] ?? null, 0, 1_000_000)
            || !is_bool($r['inStock'] ?? null) || !(($r['id'] ?? null) === null || admin_is_int($r['id']))
            || !is_array($subs) || count($subs) > 20 || array_filter($subs, fn ($s) => !is_int($s))) admin_fail($bad);
        $clean[] = ['id' => $r['id'] ?? null, 'name' => $name, 'kind' => $r['kind'], 'unit' => $r['unit'], 'priceKop' => $r['priceKop'], 'inStock' => $r['inStock'], 'substitutes' => array_values($subs)];
    }
    $names = array_map(fn ($r) => admin_norm($r['name']), $clean);
    foreach ($names as $i => $n) {
        if (array_search($n, $names, true) !== $i) admin_fail('Два цветка с одинаковым названием: ' . $clean[array_search($n, $names, true)]['name']);
    }

    try {
        Db::tx(function () use ($clean, $names) {
            $existing = [];
            foreach (Db::all('SELECT * FROM flowers') as $f) $existing[(int) $f['id']] = $f;
            // Строка без id, но с названием уже существующего цветка (например, страницу не обновили
            // после прошлого сохранения) — это тот же цветок, обновляем его, а не создаём второй.
            $plan = [];
            foreach ($clean as $i => $r) {
                $old = $r['id'] !== null ? ($existing[$r['id']] ?? null) : null;
                if ($r['id'] === null) {
                    foreach ($existing as $f) if (admin_norm($f['name']) === $names[$i]) $old = $f;
                }
                $plan[] = [$r, $i, $old];
            }
            $planned = array_filter(array_map(fn ($p) => $p[2] ? (int) $p[2]['id'] : null, $plan));
            // Цветок, которого нет в списке (удалён в другой вкладке и т.п.), но с тем же названием, что у строки, — мешает.
            foreach ($existing as $f) {
                if (!in_array((int) $f['id'], $planned, true) && in_array(admin_norm($f['name']), $names, true)) {
                    throw new AdminError("Цветок «{$f['name']}» уже есть в базе. Обновите страницу (F5) и попробуйте ещё раз.");
                }
            }
            // Сначала временные названия: так можно поменять двум цветкам названия местами.
            foreach ($plan as [, , $old]) if ($old) Db::update('flowers', (int) $old['id'], ['name' => "__tmp_{$old['id']}"]);
            foreach ($plan as [$r, $i, $old]) {
                $row = ['name' => $r['name'], 'kind' => $r['kind'], 'unit' => $r['unit'], 'priceKop' => $r['priceKop'], 'inStock' => (int) $r['inStock'], 'sortOrder' => $i];
                if ($old) {
                    $id = (int) $old['id'];
                    $row['prevPriceKop'] = (int) $old['priceKop'] !== $r['priceKop'] ? (int) $old['priceKop'] : $old['prevPriceKop'];
                    Db::update('flowers', $id, $row);
                    Db::exec('DELETE FROM flower_substitutes WHERE flowerId = ?', [$id]);
                } else {
                    $id = Db::insert('flowers', $row);
                }
                foreach (array_unique($r['substitutes']) as $sub) {
                    if ($sub !== $id && isset($existing[$sub])) Db::insert('flower_substitutes', ['flowerId' => $id, 'substituteId' => $sub]);
                }
            }
        });
    } catch (AdminError $e) {
        admin_fail($e->getMessage());
    } catch (Throwable $e) {
        app_log('saveFlowers: ' . $e->getMessage());
        admin_fail('Не удалось сохранить, ничего не изменилось. Обновите страницу (F5) и попробуйте ещё раз.');
    }
    Http::json(['ok' => true, 'flowers' => admin_flowers()]);
});

Http::post('/admin/flowers/delete', function () {
    Auth::requireAdmin();
    Auth::checkCsrf();
    $id = admin_body()['id'] ?? null;
    if (!admin_is_int($id)) admin_fail('Ошибка');
    if (Db::value('SELECT COUNT(*) FROM bouquet_items WHERE flowerId = ?', [$id]) > 0) {
        admin_fail('Цветок есть в букетах. Сначала уберите его из состава или просто отметьте «Нет».');
    }
    try {
        Db::exec('DELETE FROM flowers WHERE id = ?', [$id]);
    } catch (Throwable $e) {
        app_log('deleteFlower: ' . $e->getMessage());
        admin_fail('Не удалось удалить цветок. Обновите страницу (F5) и попробуйте ещё раз.');
    }
    Http::json(['ok' => true]);
});

// ---------- Коллекции ----------

Http::get('/admin/collections', function () {
    Auth::requireAdmin();
    $list = Db::all('SELECT * FROM collections ORDER BY name');
    $bouquets = Db::all('SELECT id, name, format, enabled, collectionId FROM bouquets WHERE collectionId IS NOT NULL ORDER BY id');
    foreach ($list as &$c) $c['bouquets'] = array_values(array_filter($bouquets, fn ($b) => $b['collectionId'] === $c['id']));
    return admin_page('collections', ['title' => 'Коллекции', 'list' => $list]);
});

Http::post('/admin/collections/create', function () {
    Auth::requireAdmin();
    Auth::checkCsrf();
    $name = Http::str('name', 120);
    if ($name !== '') Db::insert('collections', ['name' => $name, 'slug' => admin_free_slug('collections', admin_slugify($name))]);
    Http::redirect('/admin/collections');
});

Http::post('/admin/collections/rename', function () {
    Auth::requireAdmin();
    Auth::checkCsrf();
    $name = Http::str('name', 120);
    if ($name !== '') Db::exec('UPDATE collections SET name = ? WHERE id = ?', [$name, (int) Http::str('id')]);
    Http::redirect('/admin/collections');
});

Http::post('/admin/collections/delete', function () {
    Auth::requireAdmin();
    Auth::checkCsrf();
    Db::exec('DELETE FROM collections WHERE id = ?', [(int) Http::str('id')]); // букеты останутся, просто без коллекции
    Http::redirect('/admin/collections');
});

// ---------- Заказы ----------

Http::get('/admin/orders', function () {
    Auth::requireAdmin();
    $status = isset($_GET['status']) && is_string($_GET['status']) ? $_GET['status'] : null;
    $active = !$status || $status === 'active';
    if ($active) {
        [$where, $params] = ["status IN ('NEW', 'CONFIRMED', 'PHOTO_SENT', 'ON_THE_WAY')", []];
    } elseif ($status === 'all') {
        [$where, $params] = ['1', []];
    } else {
        [$where, $params] = ['status = ?', [$status]];
    }
    $orders = Db::all("SELECT * FROM orders WHERE $where ORDER BY deliveryDate, LPAD(deliverySlot, 11, '0') LIMIT 200", $params);
    $ids = array_column($orders, 'id');
    $items = $ids ? Db::all('SELECT * FROM order_items WHERE orderId ' . Db::in($ids) . ' ORDER BY id', $ids) : [];
    foreach ($orders as &$o) $o['items'] = array_values(array_filter($items, fn ($i) => $i['orderId'] === $o['id']));
    unset($o);
    $counts = [];
    foreach (Db::all('SELECT status, COUNT(*) AS n FROM orders GROUP BY status') as $c) $counts[$c['status']] = (int) $c['n'];
    // Заказы, о которых не пришло уведомление в Telegram или письмо: их нужно посмотреть здесь.
    return admin_page('orders', [
        'title' => 'Заказы',
        'status' => $status,
        'active' => $active,
        'orders' => $orders,
        'counts' => $counts,
        'unnotified' => admin_telegram_ready() ? (int) Db::value('SELECT COUNT(*) FROM orders WHERE notifiedAt IS NULL AND notifyAttempts > 0') : 0,
        'unemailed' => admin_smtp_ready() ? (int) Db::value('SELECT COUNT(*) FROM orders WHERE emailedAt IS NULL AND emailAttempts > 0') : 0,
    ]);
});

Http::get('/admin/orders/{id}', function (string $id) {
    Auth::requireAdmin();
    if (!ctype_digit($id)) Http::abort(404);
    $o = Db::one('SELECT * FROM orders WHERE id = ?', [(int) $id]) ?? Http::abort(404);
    $o['items'] = Db::all('SELECT * FROM order_items WHERE orderId = ? ORDER BY id', [$o['id']]);
    $shop = null;
    foreach (Settings::get('delivery')['shops'] as $s) if ($s['code'] === $o['shop']) $shop = $s;
    return admin_page('order', ['title' => "Заказ № {$o['number']}", 'o' => $o, 'shop' => $shop]);
});

Http::post('/admin/orders/update', function () {
    Auth::requireAdmin();
    Auth::checkCsrf();
    $id = (int) Http::str('id');
    $status = Http::str('status');
    if (array_key_exists($status, ADMIN_STATUS_LABEL)) Db::exec('UPDATE orders SET status = ? WHERE id = ?', [$status, $id]);
    if (isset($_POST['paid'])) Db::exec('UPDATE orders SET paid = ? WHERE id = ?', [(int) ($_POST['paid'] === '1'), $id]);
    Http::redirect("/admin/orders/$id");
});

// Заказ «уточнить адрес у получателя»: флорист вписывает адрес, время и доплату за доставку.
Http::post('/admin/orders/address', function () {
    Auth::requireAdmin();
    Auth::checkCsrf();
    $id = (int) Http::str('id');
    $address = trim((string) ($_POST['address'] ?? ''));
    $slot = (string) ($_POST['slot'] ?? '');
    $delivery = trim((string) ($_POST['delivery'] ?? ''));
    $o = Db::one('SELECT * FROM orders WHERE id = ?', [$id]);
    if ($o && $o['addressUnknown'] && mb_strlen($address) <= 300 && mb_strlen($slot) <= 20 && preg_match('/^\d{1,4}([.,]\d{1,2})?$/', $delivery)) {
        $deliveryKop = (int) round((float) str_replace(',', '.', $delivery) * 100);
        Db::update('orders', $id, [
            'address' => $address, 'deliverySlot' => $slot, 'deliveryKop' => $deliveryKop,
            'totalKop' => $o['itemsKop'] - $o['discountKop'] + $deliveryKop,
        ]);
    }
    Http::redirect("/admin/orders/$id");
});

// ---------- Доставка ----------

Http::get('/admin/delivery', function () {
    Auth::requireAdmin();
    return admin_page('delivery', [
        'title' => 'Доставка',
        'editor' => ['delivery' => Settings::get('delivery'), 'districts' => Settings::get('districts')],
        'head' => '<link rel="stylesheet" href="' . View::asset('vendor/leaflet/leaflet.css') . '">',
        'scripts' => '<script src="' . View::asset('vendor/leaflet/leaflet.js') . '"></script>' . admin_scripts('delivery-zones.js', 'admin-delivery.js'),
    ]);
});

function admin_latlng(mixed $p): ?array
{
    if (!is_array($p) || !admin_is_num($p['lat'] ?? null) || !admin_is_num($p['lng'] ?? null)) return null;
    if (abs($p['lat']) > 90 || abs($p['lng']) > 180) return null;
    return ['lat' => (float) $p['lat'], 'lng' => (float) $p['lng']];
}

function admin_hex(mixed $c): bool
{
    return is_string($c) && preg_match('/^#[0-9a-f]{6}$/i', $c);
}

// Раздел «Доставка»: зоны, магазины и районы с карты.
Http::post('/admin/delivery/save', function () {
    Auth::requireAdmin();
    Auth::checkCsrf();
    $in = admin_body();
    $d = $in['delivery'] ?? null;
    $bad = fn (string $what) => admin_fail("Проверьте поля: $what");
    if (!is_array($d)) $bad('доставка');

    $shops = [];
    if (!is_array($d['shops'] ?? null) || !$d['shops']) $bad('магазины');
    foreach ($d['shops'] as $s) {
        $loc = admin_latlng($s['location'] ?? null);
        if (!is_string($s['code'] ?? null) || !preg_match('/^[a-z0-9-]+$/', $s['code']) || !is_string($s['name'] ?? null) || $s['name'] === ''
            || mb_strlen($s['name']) > 60 || admin_str($s['address'] ?? null, 200) === null || admin_str($s['hours'] ?? null, 60) === null || !$loc) $bad('магазины');
        $mapUrl = trim((string) ($s['mapUrl'] ?? ''));
        if ($mapUrl !== '' && (mb_strlen($mapUrl) > 300 || !preg_match('~^https://~', $mapUrl))) admin_fail('Ссылка на карту должна начинаться с https://');
        $shops[] = ['code' => $s['code'], 'name' => $s['name'], 'address' => $s['address'], 'hours' => $s['hours'], 'mapUrl' => $mapUrl, 'location' => $loc];
    }

    $zones = [];
    if (!is_array($d['zones'] ?? null) || !$d['zones'] || count($d['zones']) > 10) $bad('зоны');
    foreach ($d['zones'] as $z) {
        if (!admin_is_num($z['maxKm'] ?? null) || $z['maxKm'] <= 0) admin_fail('Проверьте расстояние у зон');
        if (!admin_is_int($z['priceKop'] ?? null, 0) || (isset($z['color']) && !admin_hex($z['color']))) $bad('зоны');
        $zones[] = ['maxKm' => $z['maxKm'], 'priceKop' => $z['priceKop']] + (isset($z['color']) ? ['color' => $z['color']] : []);
    }
    usort($zones, fn ($a, $b) => $a['maxKm'] <=> $b['maxKm']);

    $areas = [];
    $rawAreas = $d['areas'] ?? [];
    if (!is_array($rawAreas) || count($rawAreas) > 40) $bad('участки');
    foreach ($rawAreas as $a) {
        $name = is_string($a['name'] ?? null) ? trim($a['name']) : '';
        if ($name === '' || mb_strlen($name) > 40) admin_fail('Проверьте названия участков');
        if (!admin_hex($a['color'] ?? null) || !admin_is_int($a['priceKop'] ?? null, 0) || !is_bool($a['noDelivery'] ?? null) || !is_array($a['points'] ?? null)) $bad('участки');
        if (count($a['points']) < 3) admin_fail('Проверьте участки: нужно хотя бы 3 точки');
        if (count($a['points']) > 300) $bad('участки');
        $points = array_map('admin_latlng', $a['points']);
        if (in_array(null, $points, true)) $bad('участки');
        $areas[] = ['name' => $name, 'color' => $a['color'], 'priceKop' => $a['priceKop'], 'noDelivery' => $a['noDelivery'], 'points' => $points];
    }

    $center = admin_latlng($d['mkadCenter'] ?? null);
    $num = fn ($k, $min, $max) => admin_is_num($d[$k] ?? null) && $d[$k] >= $min && $d[$k] <= $max;
    if (!$center || !$num('mkadRadiusKm', 0.001, 50) || !admin_is_int($d['outsideMkadKop'] ?? null, 0) || !admin_is_int($d['freeFromKop'] ?? null, 0)
        || !$num('pickupDiscountPct', 0, 50) || !$num('maxOutsideMkadKm', 0, 200) || (isset($d['outsideColor']) && !admin_hex($d['outsideColor']))) $bad('доставка');

    $districts = [];
    $rawDistricts = $in['districts'] ?? null;
    if (!is_array($rawDistricts) || count($rawDistricts) > 80) $bad('районы');
    foreach ($rawDistricts as $x) {
        $name = is_string($x['name'] ?? null) ? trim($x['name']) : '';
        if ($name === '' || mb_strlen($name) > 40) admin_fail('Проверьте названия районов');
        if (!admin_is_num($x['lat'] ?? null) || !admin_is_num($x['lng'] ?? null)) $bad('районы');
        $districts[] = ['name' => $name, 'lat' => $x['lat'], 'lng' => $x['lng']];
    }
    if (count(array_unique(array_column($districts, 'name'))) !== count($districts)) admin_fail('Проверьте районы: два с одинаковым названием');

    $cfg = [
        'shops' => $shops, 'zones' => $zones, 'areas' => $areas, 'mkadCenter' => $center, 'mkadRadiusKm' => $d['mkadRadiusKm'],
        'outsideMkadKop' => $d['outsideMkadKop'], 'freeFromKop' => $d['freeFromKop'], 'pickupDiscountPct' => $d['pickupDiscountPct'],
        'maxOutsideMkadKm' => $d['maxOutsideMkadKm'],
    ];
    if (isset($d['outsideColor'])) $cfg['outsideColor'] = $d['outsideColor'];
    Settings::save('delivery', $cfg);
    Settings::save('districts', $districts);
    Http::json(['ok' => true]);
});

// ---------- Настройки ----------

function admin_addons(): array
{
    return array_map(fn ($a) => [
        'id' => (int) $a['id'], 'code' => $a['code'], 'name' => $a['name'], 'priceKop' => (int) $a['priceKop'],
        'included' => (bool) $a['included'], 'active' => (bool) $a['active'],
    ], Db::all('SELECT * FROM addons ORDER BY sortOrder'));
}

function admin_emails_ok(string $list): bool
{
    foreach (explode(',', $list) as $e) {
        if (trim($e) !== '' && !filter_var(trim($e), FILTER_VALIDATE_EMAIL)) return false;
    }
    return true;
}

Http::get('/admin/settings', function () {
    Auth::requireAdmin();
    return admin_page('settings', [
        'title' => 'Настройки',
        'editor' => [
            'shop' => Settings::get('shop'),
            'colors' => Settings::get('colors'),
            'addons' => admin_addons(),
            'bouquets' => Db::all('SELECT slug, name FROM bouquets WHERE enabled = 1 ORDER BY name'),
            'smtpReady' => admin_smtp_ready(),
        ],
        'scripts' => admin_scripts('admin-settings.js'),
    ]);
});

Http::post('/admin/settings/save', function () {
    Auth::requireAdmin();
    Auth::checkCsrf();
    $in = admin_body();
    $s = $in['shop'] ?? null;
    if (!is_array($s)) admin_fail('Проверьте поля: shop');
    $limits = ['phone' => 40, 'viber' => 300, 'telegram' => 300, 'whatsapp' => 300, 'instagram' => 300, 'sellerDetails' => 1000, 'promoLine' => 300, 'orderEmail' => 300, 'dayBouquet' => 200, 'email' => 200, 'complaints' => 300, 'authority' => 300];
    $shop = [];
    foreach ($limits as $k => $max) {
        $v = $s[$k] ?? (in_array($k, ['whatsapp', 'orderEmail', 'dayBouquet', 'email', 'complaints', 'authority'], true) ? '' : null);
        if (admin_str($v, $max) === null) admin_fail("Проверьте поля: shop → $k");
        $shop[$k] = $v;
    }
    if (!admin_is_num($s['leadHours'] ?? null) || $s['leadHours'] < 0 || $s['leadHours'] > 24) admin_fail('Проверьте поля: shop → leadHours');
    $shop['leadHours'] = $s['leadHours'];
    if (!admin_emails_ok($shop['orderEmail'])) admin_fail('Проверьте почту для заказов');

    $colors = [];
    $raw = $in['colors'] ?? null;
    if (!is_array($raw) || !$raw || count($raw) > 40) admin_fail('Проверьте поля: colors');
    foreach ($raw as $c) {
        $label = is_string($c['label'] ?? null) ? trim($c['label']) : '';
        if ($label === '' || mb_strlen($label) > 30) admin_fail('Проверьте названия цветов');
        // свой цвет — #rrggbb из палитры; «Микс» — разноцветный кружок
        if (!is_string($c['slug'] ?? null) || !preg_match('/^[a-z0-9-]{1,30}$/', $c['slug']) || !is_string($c['swatch'] ?? null) || strlen($c['swatch']) > 200
            || !preg_match('/^#[0-9a-f]{6}$|^conic-gradient\([#0-9a-f, ]+\)$/i', $c['swatch'])) admin_fail('Проверьте поля: colors');
        $colors[] = ['slug' => $c['slug'], 'label' => $label, 'swatch' => $c['swatch']];
    }
    if (count(array_unique(array_column($colors, 'slug'))) !== count($colors)) admin_fail('Проверьте поля: colors');

    $addons = $in['addons'] ?? null;
    if (!is_array($addons) || count($addons) > 50) admin_fail('Проверьте дополнения');
    foreach ($addons as $a) {
        $name = is_string($a['name'] ?? null) ? trim($a['name']) : '';
        if ($name === '' || mb_strlen($name) > 80 || !is_string($a['code'] ?? null) || !preg_match('/^[a-z0-9-]{1,40}$/', $a['code'])
            || !admin_is_int($a['priceKop'] ?? null, 0) || !is_bool($a['included'] ?? null) || !is_bool($a['active'] ?? null)
            || !(($a['id'] ?? null) === null || admin_is_int($a['id']))) admin_fail('Проверьте дополнения');
    }

    Settings::save('shop', $shop);
    Settings::save('colors', $colors);
    try {
        // по code (он уникальный): новое дополнение, сохранённое второй раз без обновления страницы, не создаётся дважды
        Db::tx(function () use ($addons) {
            foreach (array_values($addons) as $i => $a) {
                $row = ['code' => $a['code'], 'name' => trim($a['name']), 'priceKop' => $a['priceKop'], 'included' => (int) $a['included'], 'active' => (int) $a['active'], 'sortOrder' => $i];
                if (isset($a['id'])) {
                    Db::update('addons', $a['id'], $row);
                } else {
                    Db::exec('INSERT INTO addons (code, name, priceKop, included, active, sortOrder) VALUES (?, ?, ?, ?, ?, ?)
                        ON DUPLICATE KEY UPDATE name = VALUES(name), priceKop = VALUES(priceKop), included = VALUES(included), active = VALUES(active), sortOrder = VALUES(sortOrder)', array_values($row));
                }
            }
        });
    } catch (Throwable $e) {
        app_log('saveAddons: ' . $e->getMessage());
        admin_fail('Не удалось сохранить дополнения. Обновите страницу (F5) и попробуйте ещё раз.');
    }
    Http::json(['ok' => true, 'addons' => admin_addons()]);
});

// Кнопка «Отправить пробное письмо»: шлём на адреса из поля (даже если ещё не сохранены).
Http::post('/admin/settings/test-email', function () {
    Auth::requireAdmin();
    Auth::checkCsrf();
    $to = admin_body()['to'] ?? '';
    $list = array_values(array_filter(array_map('trim', explode(',', is_string($to) ? $to : ''))));
    if (count($list) > 5 || array_filter($list, fn ($e) => !filter_var($e, FILTER_VALIDATE_EMAIL))) {
        admin_fail('Проверьте почту для заказов: адреса через запятую, не больше пяти');
    }
    if (!admin_smtp_ready()) admin_fail('Почта-отправитель не настроена: в app/config.php нет smtp_host, smtp_user или smtp_pass');
    if (!$list) admin_fail('Впишите выше, на какую почту присылать письма о заказах');
    // Само письмо отправляет Notify (часть «Корзина и заказ»)
    if (!class_exists('Notify') || !method_exists('Notify', 'sendTestEmail')) admin_fail('Отправка писем ещё не подключена');
    Http::json(Notify::sendTestEmail(implode(', ', $list)));
});

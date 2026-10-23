<?php
// Служебные команды (в панели хостинга → «Shell-клиент», из папки сайта):
//   php app/cli.php migrate      — применить новые таблицы базы
//   php app/cli.php demo         — добавить цветы и букеты-примеры (только в пустую базу)
//   php app/cli.php clean-demo   — убрать все букеты, заказы и напоминания (справочник цветов остаётся)
//   php app/cli.php import ~/artflora-export.json [--replace]
//                                — перенести каталог с сайта на Node.js (файл даёт prisma/export-for-php.js);
//                                  --replace сначала убирает букеты, коллекции и цветы, которые уже есть на сайте
require __DIR__ . '/bootstrap.php';
if (PHP_SAPI !== 'cli') exit;

$cmd = $argv[1] ?? '';
Db::migrate();

if ($cmd === 'migrate') {
    echo "База в порядке\n";
} elseif ($cmd === 'demo') {
    if (Db::value('SELECT COUNT(*) FROM flowers')) exit("В базе уже есть цветы, примеры не добавляю.\n");
    $demo = json_decode(file_get_contents(__DIR__ . '/demo.json'), true);
    $kop = fn ($byn) => (int) round($byn * 100);
    Db::tx(function () use ($demo, $kop) {
        $fid = [];
        foreach ($demo['flowers'] as $i => $f) {
            $fid[$f['name']] = Db::insert('flowers', ['name' => $f['name'], 'kind' => $f['kind'], 'unit' => $f['unit'], 'priceKop' => $kop($f['price']), 'inStock' => (int) ($f['inStock'] ?? true), 'sortOrder' => $i]);
        }
        $rose = Db::insert('collections', ['slug' => 'rose', 'name' => 'rose']);
        foreach ($demo['bouquets'] as $i => $b) {
            $id = Db::insert('bouquets', [
                'slug' => $b['slug'], 'name' => $b['name'], 'description' => $b['description'], 'format' => $b['format'],
                'packagingKop' => $kop($b['packaging']), 'enabled' => (int) ($b['enabled'] ?? true), 'isHit' => (int) ($b['isHit'] ?? false),
                'isExclusive' => (int) ($b['isExclusive'] ?? false), 'colors' => Db::json($b['colors'] ?? []), 'occasions' => Db::json($b['occasions']),
                'photos' => Db::json([$b['photo']]), 'sortOrder' => $i, 'collectionId' => ($b['collection'] ?? '') === 'rose' ? $rose : null,
            ]);
            // S — компактнее, L — пышнее. Количество масштабируем от M.
            $scale = fn ($k) => array_map(fn ($x) => [$x[0], max(1, (int) round($x[1] * $k))], $b['composition']);
            foreach ([['S', 'Компактнее', $scale(0.65)], ['M', 'Как на фото', $b['composition']], ['L', 'Пышнее', $scale(1.4)]] as [$code, $note, $items]) {
                $n = array_sum(array_map(fn ($x) => in_array($x[0], ['Эвкалипт', 'Гипсофила'], true) ? 0 : $x[1], $items));
                $word = $n % 10 === 1 && $n % 100 !== 11 ? 'цветок' : ($n % 10 >= 2 && $n % 10 <= 4 && ($n % 100 < 10 || $n % 100 >= 20) ? 'цветка' : 'цветков');
                $sid = Db::insert('bouquet_sizes', ['bouquetId' => $id, 'code' => $code, 'note' => "$note, $n $word", 'packagingKop' => $kop($b['packaging'])]);
                foreach ($items as [$name, $q]) Db::insert('bouquet_items', ['sizeId' => $sid, 'flowerId' => $fid[$name], 'qty' => $q]);
            }
        }
    });
    echo 'Примеры добавлены: ' . count($demo['flowers']) . ' цветов, ' . count($demo['bouquets']) . " букетов.\n";
} elseif ($cmd === 'clean-demo') {
    Db::exec('DELETE FROM orders');
    Db::exec('DELETE FROM reminders');
    Db::exec('DELETE FROM bouquets');
    Db::exec('DELETE FROM collections');
    echo "Букеты, коллекции, заказы и напоминания удалены.\n";
} elseif ($cmd === 'import') {
    $file = $argv[2] ?? exit("Укажите файл: php app/cli.php import ~/artflora-export.json\n");
    $data = is_file($file) ? json_decode(file_get_contents($file), true) : null;
    if (!is_array($data) || ($data['version'] ?? 0) !== 1) exit("Файл $file не найден или это не выгрузка каталога.\n");
    $has = Db::value('SELECT COUNT(*) FROM flowers') + Db::value('SELECT COUNT(*) FROM bouquets');
    if ($has && !in_array('--replace', $argv, true)) {
        exit("На сайте уже есть цветы или букеты. Чтобы заменить их выгрузкой, добавьте в конце команды --replace\n");
    }
    // Фото — в uploads/, имена те же, что на старом сайте
    if (!is_dir(Uploads::dir())) mkdir(Uploads::dir(), 0755, true);
    foreach ($data['photos'] ?? [] as $name => $b64) {
        $bin = base64_decode($b64, true);
        $ok = Uploads::safeName($name) && $bin !== false;
        $ok = $ok && array_filter(array_keys(Uploads::TYPES), fn ($t) => Uploads::looksLikeImage($bin, $t));
        if ($ok) file_put_contents(Uploads::dir() . '/' . $name, $bin);
        else echo "Пропущено фото $name\n";
    }
    $b = fn ($v) => (int) (bool) $v;
    Db::tx(function () use ($data, $b) {
        // Номера (id) сохраняем, чтобы составы букетов и замены цветов указывали на те же цветы
        Db::exec('DELETE FROM bouquets');
        Db::exec('DELETE FROM collections');
        Db::exec('DELETE FROM flowers');
        foreach ($data['flowers'] as $f) {
            Db::insert('flowers', ['id' => $f['id'], 'name' => $f['name'], 'kind' => $f['kind'], 'unit' => $f['unit'], 'priceKop' => $f['priceKop'],
                'prevPriceKop' => $f['prevPriceKop'], 'inStock' => $b($f['inStock']), 'sortOrder' => $f['sortOrder']]);
        }
        foreach ($data['flowers'] as $f) {
            foreach ($f['substitutes'] as $sub) Db::insert('flower_substitutes', ['flowerId' => $f['id'], 'substituteId' => $sub]);
        }
        foreach ($data['collections'] as $c) Db::insert('collections', ['id' => $c['id'], 'slug' => $c['slug'], 'name' => $c['name']]);
        foreach ($data['bouquets'] as $x) {
            Db::insert('bouquets', [
                'id' => $x['id'], 'slug' => $x['slug'], 'name' => $x['name'], 'description' => $x['description'], 'format' => $x['format'],
                'packagingKop' => $x['packagingKop'], 'enabled' => $b($x['enabled']), 'isHit' => $b($x['isHit']), 'isExclusive' => $b($x['isExclusive']),
                'photoSize' => $x['photoSize'], 'colors' => Db::json($x['colors']), 'occasions' => Db::json($x['occasions']), 'photos' => Db::json($x['photos']),
                'sortOrder' => $x['sortOrder'], 'collectionId' => $x['collectionId'],
            ]);
            foreach ($x['sizes'] as $s) {
                $sid = Db::insert('bouquet_sizes', ['bouquetId' => $x['id'], 'code' => $s['code'], 'note' => $s['note'], 'packagingKop' => $s['packagingKop']]);
                foreach ($s['items'] as $i) Db::insert('bouquet_items', ['sizeId' => $sid, 'flowerId' => $i['flowerId'], 'qty' => $i['qty']]);
            }
        }
        foreach ($data['addons'] as $a) {
            $row = ['name' => $a['name'], 'priceKop' => $a['priceKop'], 'included' => $b($a['included']), 'active' => $b($a['active']), 'sortOrder' => $a['sortOrder']];
            $id = Db::value('SELECT id FROM addons WHERE code = ?', [$a['code']]);
            $id ? Db::update('addons', (int) $id, $row) : Db::insert('addons', $row + ['code' => $a['code']]);
        }
        foreach ($data['settings'] as $key => $value) {
            if (in_array($key, ['shop', 'delivery', 'districts', 'colors'], true) && is_array($value)) Settings::save($key, $value);
        }
    });
    echo 'Перенесено: цветов ' . count($data['flowers']) . ', букетов ' . count($data['bouquets']) . ', фото ' . count($data['photos'] ?? []) . ", настройки.\n";
} else {
    echo "Команды: migrate, demo, clean-demo, import\n";
}

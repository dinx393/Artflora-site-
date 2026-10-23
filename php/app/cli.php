<?php
// Служебные команды (в панели хостинга → «Shell-клиент», из папки сайта):
//   php app/cli.php migrate      — применить новые таблицы базы
//   php app/cli.php demo         — добавить цветы и букеты-примеры (только в пустую базу)
//   php app/cli.php clean-demo   — убрать все букеты, заказы и напоминания (справочник цветов остаётся)
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
} else {
    echo "Команды: migrate, demo, clean-demo\n";
}

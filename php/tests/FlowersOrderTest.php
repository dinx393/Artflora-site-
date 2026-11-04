<?php
group('справочник «Цветы»: новые сверху в админке, в конце на сайте');
$fl = fn (string $name, int $sort, bool $fresh = false) => ['name' => $name, 'sortOrder' => $sort, 'fresh' => $fresh ? '1' : '0'];
test('в админке новые сверху, самый свежий первым', function () use ($fl) {
    $rows = [$fl('Роза', 0), $fl('Тюльпан', 1), $fl('Пион', 3, true), $fl('Гербера', 2), $fl('Лилия', 4, true)];
    eq(array_column(Catalog::adminFlowerOrder($rows), 'name'), ['Лилия', 'Пион', 'Роза', 'Тюльпан', 'Гербера']);
});
test('сохранение: обычные 0,1,2…, новые после них, верхний — с самым большим номером', function () {
    eq(Catalog::flowerSortOrders([true, true, false, false, false]), [4, 3, 0, 1, 2]);
    eq(Catalog::flowerSortOrders([false, true, false]), [0, 2, 1], 'новый, перетащенный вниз, всё равно в хвосте');
    eq(Catalog::flowerSortOrders([]), []);
});
test('сохранили и открыли снова — порядок на экране тот же', function () use ($fl) {
    $screen = [['Лилия', true], ['Пион', true], ['Роза', false], ['Тюльпан', false]];
    $orders = Catalog::flowerSortOrders(array_column($screen, 1));
    $rows = array_map(fn ($r, $o) => $fl($r[0], $o, $r[1]), $screen, $orders);
    shuffle($rows);
    eq(array_column(Catalog::adminFlowerOrder($rows), 'name'), ['Лилия', 'Пион', 'Роза', 'Тюльпан']);
    // через неделю (fresh = 0) новые уходят в конец, в порядке добавления — так и в фильтре на сайте
    $later = array_map(fn ($r) => ['fresh' => '0'] + $r, $rows);
    eq(array_column(Catalog::adminFlowerOrder($later), 'name'), ['Роза', 'Тюльпан', 'Пион', 'Лилия']);
});

<?php
group('таймер «собран сегодня» (база)');
Db::migrate();
$timerBouquet = function (string $slug, string $hideAt) {
    $f = Db::insert('flowers', ['name' => 'Тест-роза ' . $slug, 'kind' => 'FLOWER', 'unit' => 'STEM', 'priceKop' => 500, 'inStock' => 1]);
    $b = Db::insert('bouquets', ['name' => 'Тест ' . $slug, 'slug' => $slug, 'description' => '', 'enabled' => 1, 'packagingKop' => 800,
        'colors' => '[]', 'occasions' => '[]', 'sections' => '[]', 'photos' => '[]']);
    Db::exec("UPDATE bouquets SET hideAt = $hideAt WHERE id = ?", [$b]);
    $s = Db::insert('bouquet_sizes', ['bouquetId' => $b, 'code' => 'M', 'note' => '', 'packagingKop' => 800]);
    Db::insert('bouquet_items', ['sizeId' => $s, 'flowerId' => $f, 'qty' => 5]);
    return [$b, $f];
};
test('вышел срок — букет выключен, не удалён, помечен; «Букет дня» снят', function () use ($timerBouquet) {
    [$old, $f1] = $timerBouquet('test-timer-old', 'NOW() - INTERVAL 1 MINUTE');
    [$new, $f2] = $timerBouquet('test-timer-new', 'NOW() + INTERVAL 36 HOUR');
    $shop = Settings::get('shop');
    Settings::save('shop', ['dayBouquet' => 'test-timer-old'] + $shop);
    try {
        eq(Catalog::hideExpired(), 1);
        $slugs = array_column(Catalog::visible(), 'slug');
        ok(!in_array('test-timer-old', $slugs, true), 'старый букет скрыт');
        ok(in_array('test-timer-new', $slugs, true), 'свежий букет на сайте');
        $row = Db::one('SELECT enabled, hideAt, hiddenByTimerAt FROM bouquets WHERE id = ?', [$old]);
        eq((int) $row['enabled'], 0);
        eq($row['hideAt'], null);
        ok($row['hiddenByTimerAt'] !== null, 'помечен «скрыт по таймеру»');
        eq(Settings::get('shop')['dayBouquet'], '', 'галочка «Букет дня»');
        eq(Catalog::hideExpired(), 0, 'второй раз — ничего');
    } finally {
        Settings::save('shop', $shop);
        Db::exec('DELETE FROM bouquets WHERE id IN (?, ?)', [$old, $new]);
        Db::exec('DELETE FROM flowers WHERE id IN (?, ?)', [$f1, $f2]);
    }
});

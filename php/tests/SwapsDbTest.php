<?php
group('замены цветов в букете (база)');
Db::migrate();
test('замена своя у каждого букета: с заменой — на сайте и «будет замена» в заказе, без замены — скрыт', function () {
    $white = Db::insert('flowers', ['name' => 'Тест-альстра белая', 'kind' => 'FLOWER', 'unit' => 'STEM', 'priceKop' => 400, 'inStock' => 0]);
    $pink = Db::insert('flowers', ['name' => 'Тест-альстра розовая', 'kind' => 'FLOWER', 'unit' => 'STEM', 'priceKop' => 450, 'inStock' => 1]);
    $rose = Db::insert('flowers', ['name' => 'Тест-роза для замен', 'kind' => 'FLOWER', 'unit' => 'STEM', 'priceKop' => 500, 'inStock' => 1]);
    $make = function (string $slug) use ($white, $rose) {
        $b = Db::insert('bouquets', ['name' => 'Тест ' . $slug, 'slug' => $slug, 'description' => '', 'enabled' => 1, 'packagingKop' => 800,
            'colors' => '[]', 'occasions' => '[]', 'sections' => '[]', 'photos' => '[]']);
        $s = Db::insert('bouquet_sizes', ['bouquetId' => $b, 'code' => 'M', 'note' => '', 'packagingKop' => 800]);
        Db::insert('bouquet_items', ['sizeId' => $s, 'flowerId' => $rose, 'qty' => 5]);
        Db::insert('bouquet_items', ['sizeId' => $s, 'flowerId' => $white, 'qty' => 2]);
        return $b;
    };
    $with = $make('test-swap-with');
    $without = $make('test-swap-without');
    Db::insert('bouquet_swaps', ['bouquetId' => $with, 'flowerId' => $white, 'substituteId' => $pink, 'sortOrder' => 0]);
    try {
        $slugs = array_column(Catalog::visible(), 'slug');
        ok(in_array('test-swap-with', $slugs, true), 'с заменой — на сайте');
        ok(!in_array('test-swap-without', $slugs, true), 'без замены — скрыт');
        $v = Catalog::bySlug('test-swap-with');
        eq($v['priceKop'], 5 * 500 + 2 * 400 + 800 + 400, 'цена прежняя (по белой), округлена до 5 BYN');
        contains($v['sizes'][0]['composition'], 'Тест-альстра розовая (вместо: тест-альстра белая) × 2');
        $cart = Checkout::priceCart(['lines' => [['bouquetId' => $with, 'size' => 'M', 'qty' => 1]], 'addons' => []]);
        eq($cart['lines'][0]['swaps'], 'Тест-альстра розовая вместо: тест-альстра белая');
        $o = ['number' => 1, 'totalKop' => 100, 'items' => [['name' => 'Тест', 'size' => 'M', 'qty' => 1, 'swaps' => $cart['lines'][0]['swaps']]],
            'deliveryMethod' => 'PICKUP', 'shop' => 'x', 'deliveryDate' => '2026-10-31', 'deliverySlot' => '', 'customerName' => 'a', 'customerPhone' => '1', 'id' => 1];
        contains(Notify::telegramText($o), '🔁 Будет замена: Тест-альстра розовая вместо: тест-альстра белая');
        Db::exec('UPDATE flowers SET inStock = 0 WHERE id = ?', [$pink]);
        ok(!Catalog::bySlug('test-swap-with'), 'замены тоже нет — скрыт');
    } finally {
        Db::exec('DELETE FROM bouquets WHERE id IN (?, ?)', [$with, $without]);
        Db::exec('DELETE FROM flowers WHERE id IN (?, ?, ?)', [$white, $pink, $rose]);
    }
});

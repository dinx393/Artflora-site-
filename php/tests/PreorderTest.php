<?php
group('предзаказ');
// Время — минское (UTC+3). Закупка в четверг, срок по умолчанию: среда 23:59, цветы в магазине со среды следующей недели.
$cfg = ['deadlineDay' => 3, 'deadlineTime' => '23:59', 'readyDay' => 3];
$at = fn (string $minsk) => strtotime($minsk . ' UTC') - 3 * 3600;

test('из воскресенья: успеваем к закупке в среду, доставка с 11 ноября', function () use ($cfg, $at) {
    eq(Preorder::window($at('2026-11-01 13:00'), $cfg), ['deadline' => '2026-11-04 23:59', 'from' => '2026-11-11']);
});
test('в среду вечером ещё успеваем, после — уже следующая неделя', function () use ($cfg, $at) {
    eq(Preorder::window($at('2026-11-04 23:58'), $cfg)['from'], '2026-11-11');
    eq(Preorder::window($at('2026-11-05 00:30'), $cfg)['from'], '2026-11-18');
});
test('пятница → следующая пятница: закупка уже сделана (пример владельца)', function () use ($cfg, $at) {
    ok(!Preorder::guaranteed('2026-11-13', $at('2026-11-06 10:00'), $cfg));
    ok(Preorder::guaranteed('2026-11-18', $at('2026-11-06 10:00'), $cfg));
});
test('понедельник → следующий понедельник: тоже уже закуплено', function () use ($cfg, $at) {
    ok(!Preorder::guaranteed('2026-11-09', $at('2026-11-02 10:00'), $cfg));
    ok(Preorder::guaranteed('2026-11-11', $at('2026-11-02 10:00'), $cfg));
});
test('срок в четверг — поставка всё равно в среду следующей недели', function () use ($at) {
    eq(Preorder::window($at('2026-11-02 10:00'), ['deadlineDay' => 4, 'deadlineTime' => '18:00', 'readyDay' => 3])['from'], '2026-11-11');
});
test('тексты для покупателя', function () use ($cfg) {
    eq(Preorder::dateText('2026-11-11'), '11 ноября (среда)');
    eq(Preorder::deadlineText($cfg), 'среды 23:59');
    eq(Preorder::cfg(['preorderDeadlineDay' => 2, 'preorderDeadlineTime' => '9:30', 'preorderReadyDay' => 9]), ['deadlineDay' => 2, 'deadlineTime' => '09:30', 'readyDay' => 3]);
});
test('букет-предзаказ: цветов нет, но все размеры доступны, цена та же', function () {
    $b = ['enabled' => true, 'photoSize' => 'M', 'sizes' => [[
        'code' => 'M', 'note' => '', 'packagingKop' => 500,
        'items' => [['qty' => 5, 'flower' => ['id' => 1, 'name' => 'Пион', 'priceKop' => 1000, 'inStock' => false, 'substitutes' => []]]],
    ]]];
    ok(!Pricing::visibility($b)['visible'], 'в общем каталоге скрыт');
    $p = Preorder::asInStock($b);
    ok(Pricing::visibility($p)['visible'], 'в предзаказе виден');
    eq(Pricing::sizePriceKop($p['sizes'][0]), Pricing::sizePriceKop($b['sizes'][0]));
});

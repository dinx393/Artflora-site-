<?php
// «Закупка и списание»: расчёты без базы (Stock, Recognize::parse)
group('Закупка и списание');

test('сумма строки делится по магазинам без потери копеек', function () {
    eq(Stock::splitSum(69350, ['uruchye' => 16, 'malinovka' => 15]), ['uruchye' => 35794, 'malinovka' => 33556]);
    eq(array_sum(Stock::splitSum(1001, ['a' => 1, 'b' => 1, 'c' => 1])), 1001);
    eq(Stock::splitSum(500, ['a' => 3, 'b' => 0]), ['a' => 500]);
});

test('цена штуки — из последней накладной на дату, иначе из первой после', function () {
    $h = [7 => [['2026-10-01', 10, 1000], ['2026-10-08', 20, 3000], ['2026-10-08', 20, 1000]]];
    eq(Stock::unitCost($h, 7, '2026-10-05'), 100.0);
    eq(Stock::unitCost($h, 7, '2026-10-20'), 100.0); // 8-го: 40 шт за 4000
    eq(Stock::unitCost($h, 7, '2026-09-01'), 100.0);
    eq(Stock::unitCost($h, 8, '2026-10-05'), null);
});

test('пришло − списали = продали, по магазинам и всего', function () {
    $items = [1 => ['Роза', 'FLOWER', 900], 2 => ['Коробка бархат S', 'PACK', null]];
    $history = [1 => [['2026-10-02', 150, 63000]], 2 => [['2026-10-02', 12, 6000]]];
    $lines = [
        ['itemId' => 1, 'shop' => 'uruchye', 'qty' => 100, 'sumKop' => 42000],
        ['itemId' => 1, 'shop' => 'malinovka', 'qty' => 50, 'sumKop' => 21000],
        ['itemId' => 2, 'shop' => 'uruchye', 'qty' => 6, 'sumKop' => 3000],
        ['itemId' => 2, 'shop' => 'malinovka', 'qty' => 6, 'sumKop' => 3000],
    ];
    $wo = [
        ['itemId' => 1, 'shop' => 'uruchye', 'qty' => 10, 'woDate' => '2026-10-10', 'reason' => 'wilted'],
        ['itemId' => 1, 'shop' => 'malinovka', 'qty' => 5, 'woDate' => '2026-10-11', 'reason' => 'broken'],
    ];
    $r = Stock::report($lines, $wo, $items, $history, ['uruchye', 'malinovka']);
    $u = $r['shops']['uruchye'];
    eq($u['buyKop'], 45000);
    eq($u['woKop'], 4200);            // 10 × 4,20
    eq($u['soldKop'], 90 * 900);      // продали 90 роз по 9 BYN
    eq($u['earnedKop'], 90 * 900 - 42000);
    eq($r['items'][0]['name'], 'Роза');
    eq($r['items'][0]['sold'], 135.0);
    eq($r['items'][0]['woPct'], 10);
    eq($r['items'][1]['soldKop'], 0); // у упаковки розничной цены нет
    eq($r['total']['woKop'], 4200 + 2100);
    eq($r['total']['sold'], 135.0);
    eq($r['reasons'], ['wilted' => 4200, 'broken' => 2100]);

    $m = Stock::report($lines, $wo, $items, $history, ['uruchye', 'malinovka'], 'malinovka');
    eq($m['items'][0]['in'], 50.0);
    eq($m['total']['woKop'], 2100);
    eq($m['reasons'], ['broken' => 2100]);
});

test('списали больше, чем пришло за период: продано на 0, без минуса в деньгах', function () {
    $r = Stock::report([], [['itemId' => 1, 'shop' => 'u', 'qty' => 3, 'woDate' => '2026-10-01', 'reason' => 'wilted']], [1 => ['Роза', 'FLOWER', 900]], [], ['u']);
    eq($r['items'][0]['sold'], -3.0);
    eq($r['items'][0]['soldKop'], 0);
    ok($r['items'][0]['noCost'], 'нет цены закупки — отмечено');
});

test('продано на — по розничной цене на день накладной и списания', function () {
    $prices = [1 => [['2000-01-01', 800], ['2026-11-10', 1000]]];
    eq(Stock::retailAt($prices, 1, '2026-11-09', 5), 800);
    eq(Stock::retailAt($prices, 1, '2026-11-10', 5), 1000);
    eq(Stock::retailAt($prices, 2, '2026-11-10', 5), 5);
    $items = [1 => ['Роза', 'FLOWER', 1000]];
    $lines = [
        ['itemId' => 1, 'shop' => 'u', 'qty' => 10, 'sumKop' => 4000, 'docDate' => '2026-11-03'],
        ['itemId' => 1, 'shop' => 'u', 'qty' => 10, 'sumKop' => 4000, 'docDate' => '2026-11-10'],
    ];
    $wo = [['itemId' => 1, 'shop' => 'u', 'qty' => 2, 'woDate' => '2026-11-05', 'reason' => 'wilted']];
    $r = Stock::report($lines, $wo, $items, [], ['u'], null, $prices);
    eq($r['shops']['u']['soldKop'], 8 * 800 + 10 * 1000);
    eq(Stock::report($lines, $wo, $items, [], ['u'])['shops']['u']['soldKop'], 18 * 1000); // без истории — текущая
});

test('периоды кнопками', function () {
    eq(Stock::period('month', null, null, '2026-11-15'), ['2026-11-01', '2026-11-30']);
    eq(Stock::period('prev', null, null, '2026-03-31'), ['2026-02-01', '2026-02-28']);
    eq(Stock::period('year', null, null, '2026-11-15'), ['2026-01-01', '2026-12-31']);
    eq(Stock::period('week', null, null, '2026-11-15'), ['2026-11-09', '2026-11-15']);
    eq(Stock::period('custom', '2026-11-20', '2026-11-01', '2026-11-15'), ['2026-11-01', '2026-11-20']);
    eq(Stock::period('custom', 'вчера', null, '2026-11-15'), ['2026-11-01', '2026-11-30']);
});

test('название строки → позиция: запомненное, точное, подсказка', function () {
    $items = [['id' => 1, 'name' => 'Коробка бархат S'], ['id' => 2, 'name' => 'Роза Эквадор 60 см']];
    $aliases = [Stock::norm('720837/3 Коробка цилиндр без крышки "Бархатный" Белый 12,5*16,9 см') => 1];
    eq(Stock::match('720837/3 Коробка цилиндр без крышки «Бархатный» белый  12,5*16,9 см', $items, $aliases), 1);
    eq(Stock::match('коробка БАРХАТ s', $items, []), 1);
    eq(Stock::match('Роза Эквадор', $items, [], 'Роза Эквадор 60 см'), 2);
    eq(Stock::match('Хризантема', $items, []), null);
});

test('Excel: точка с запятой, числа с запятой, формулы не исполняются', function () {
    $csv = Stock::csv([['Роза', 12.5, 3], ['=1+1', 'a"b']]);
    eq($csv, "\xEF\xBB\xBF\"Роза\";12,5;3\r\n\"'=1+1\";\"a\"\"b\"\r\n");
});

test('ответ распознавания: текст JSON, отказ, ошибка ключа', function () {
    $ok = json_encode(['stop_reason' => 'end_turn', 'content' => [['type' => 'thinking', 'thinking' => ''], ['type' => 'text', 'text' => '{"lines":[{"name":"Роза"}]}']]]);
    eq(Recognize::parse(200, $ok), ['ok' => true, 'data' => ['lines' => [['name' => 'Роза']]]]);
    eq(Recognize::parse(200, json_encode(['stop_reason' => 'refusal', 'content' => []]))['ok'], false);
    contains(Recognize::parse(401, '{"error":{"message":"invalid x-api-key"}}')['error'], 'Ключ');
    contains(Recognize::parse(529, '{}')['error'], 'занят');
});

test('распознавание без ключа не ходит в интернет', function () {
    $old = Config::all();
    Config::override(array_merge($old, ['anthropic_api_key' => '']));
    Recognize::$transport = function () { throw new RuntimeException('не должны звать'); };
    $r = Recognize::invoice([['image/jpeg', 'x']], []);
    Recognize::$transport = null;
    Config::override($old);
    eq($r['ok'], false);
});

test('запрос распознавания: фото, схема ответа, список позиций', function () {
    $old = Config::all();
    Config::override(array_merge($old, ['anthropic_api_key' => 'test']));
    $sent = null;
    Recognize::$transport = function (array $body) use (&$sent) {
        $sent = $body;
        return [200, json_encode(['stop_reason' => 'end_turn', 'content' => [['type' => 'text', 'text' => '{"date":"","lines":[]}']]])];
    };
    $r = Recognize::writeoff([['image/jpeg', 'abc']], ['Роза'], [['uruchye', 'Уручье', ''], ['malinovka', 'Малиновка', 'ул. Яна Чечота, 7']]);
    Recognize::$transport = null;
    Config::override($old);
    eq($r['ok'], true);
    eq($sent['model'], 'claude-opus-5-5');
    eq($sent['messages'][0]['content'][0]['source']['data'], base64_encode('abc'));
    eq($sent['output_config']['format']['type'], 'json_schema');
    eq($sent['output_config']['format']['schema']['properties']['lines']['items']['properties']['shop']['enum'], ['uruchye', 'malinovka', '']);
    contains($sent['messages'][0]['content'][1]['text'], 'Чечота');
    contains($sent['messages'][0]['content'][1]['text'], '- Роза');
});

test('деньги по банку: пришло, ушло, итог, по категориям', function () {
    $r = Stock::money([
        ['direction' => 'IN', 'category' => 'acquiring', 'amountKop' => 120000],
        ['direction' => 'IN', 'category' => 'erip', 'amountKop' => 30000],
        ['direction' => 'OUT', 'category' => 'supplier', 'amountKop' => 69350],
        ['direction' => 'OUT', 'category' => 'salary', 'amountKop' => 90000],
        ['direction' => 'IN', 'category' => 'acquiring', 'amountKop' => 5000],
    ]);
    eq([$r['inKop'], $r['outKop'], $r['netKop']], [155000, 159350, -4350]);
    eq($r['byIn'], ['acquiring' => 125000, 'erip' => 30000]);
    eq(array_keys($r['byOut']), ['salary', 'supplier']);
});

test('деньги по банку: дивиденды, корп. карточка и займ СН — отдельно «Директору», не расходы бизнеса', function () {
    $r = Stock::money([
        ['direction' => 'IN', 'category' => 'acquiring', 'amountKop' => 300000],
        ['direction' => 'OUT', 'category' => 'supplier', 'amountKop' => 200000],
        ['direction' => 'OUT', 'category' => 'dividends', 'amountKop' => 50000],
        ['direction' => 'OUT', 'category' => 'corp_card', 'amountKop' => 50000],
        ['direction' => 'OUT', 'category' => 'loan_sn', 'amountKop' => 30000],
    ]);
    eq([$r['outKop'], $r['netKop'], $r['ownerKop'], $r['afterOwnerKop']], [200000, 100000, 130000, -30000]);
    eq(array_keys($r['byOut']), ['supplier']);
    eq($r['byOwner'], ['dividends' => 50000, 'corp_card' => 50000, 'loan_sn' => 30000]);
});

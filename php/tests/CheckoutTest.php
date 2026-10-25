<?php
group('оформление заказа');
$order = [
    'cart' => ['lines' => [['bouquetId' => 1, 'size' => 'M', 'qty' => 1]], 'addons' => [], 'cardText' => ''],
    'method' => 'DELIVERY', 'address' => 'пр. Независимости, 180', 'district' => ' Уручье ', 'date' => '2026-10-24', 'slot' => '14:00–15:00',
    'customerName' => ' Анна ', 'customerPhone' => '+375 (29) 111-22-33', 'payment' => 'ERIP', 'consent' => true,
];
test('корзина: только id, размер и количество; цены из браузера отбрасываются', function () {
    $c = Checkout::parseCart(['lines' => [['bouquetId' => 5, 'size' => 'L', 'qty' => 2, 'priceKop' => 1]], 'addons' => [], 'cardText' => 'Люблю']);
    eq($c, ['lines' => [['bouquetId' => 5, 'size' => 'L', 'qty' => 2]], 'addons' => [], 'cardText' => 'Люблю']);
    eq(Checkout::parseCart(['lines' => [['bouquetId' => 5, 'size' => 'XXXL', 'qty' => 1]]]), null, 'неизвестный размер');
    eq(Checkout::parseCart(['lines' => [['bouquetId' => 5, 'size' => 'M', 'qty' => 21]]]), null, 'больше 20 штук');
    eq(Checkout::parseCart(['lines' => [['bouquetId' => '5', 'size' => 'M', 'qty' => 1]]]), null, 'id строкой');
});
test('заявка: пробелы убраны, телефон только цифрами', function () use ($order) {
    $v = Checkout::validate($order);
    eq($v['ok'], true);
    eq($v['order']['district'], 'Уручье');
    eq($v['order']['customerName'], 'Анна');
    eq($v['order']['customerPhone'], '+375291112233');
});
test('заявка: ошибки с полем', function () use ($order) {
    eq(Checkout::validate(['customerName' => ''] + $order)['field'], 'customerName');
    eq(Checkout::validate(['customerPhone' => '123'] + $order)['field'], 'customerPhone');
    eq(Checkout::validate(['address' => 'ул'] + $order)['field'], 'address');
    eq(Checkout::validate(['payment' => 'CASH'] + $order)['field'], 'payment');
    eq(Checkout::validate(['consent' => false] + $order)['field'], 'consent');
    eq(Checkout::validate(['addressUnknown' => true, 'address' => ''] + $order)['field'], 'recipientPhone');
    eq(Checkout::validate(['addressUnknown' => true, 'address' => '', 'recipientPhone' => '+375291234567'] + $order)['ok'], true);
    eq(Checkout::validate(['method' => 'PICKUP', 'address' => ''] + $order)['ok'], true);
    eq(Checkout::validate(['cart' => ['lines' => [], 'addons' => [], 'cardText' => '']] + $order)['error'], 'Корзина пуста');
    eq(Checkout::validate(['idempotencyKey' => 'abc'] + $order)['ok'], false);
});
test('безнал для организаций: без реквизитов заказа нет', function () use ($order) {
    $text = "ООО «Ромашка»\r\nУНП 190000001\nг. Минск, ул. Цветочная, 1\nр/с BY13 NBRB 3600 9000 0000 2Z00 AB00 в Нацбанке, БИК NBRBBY2X";
    $inv = ['payment' => 'INVOICE'] + $order;
    eq(Checkout::validate($inv)['field'], 'company', 'реквизитов нет совсем');
    eq(Checkout::validate(['company' => ['text' => 'ООО «Ромашка»']] + $inv)['field'], 'company', 'слишком коротко');
    eq(Checkout::validate(['company' => ['text' => 'ООО «Ромашка», г. Минск, ул. Цветочная, 1']] + $inv)['error'], 'В реквизитах не хватает УНП (9 цифр)');
    $v = Checkout::validate(['company' => ['text' => "  $text  "]] + $inv);
    eq($v['ok'], true);
    eq($v['order']['company']['text'], str_replace("\r", '', $text));
    eq(Checkout::validate(['company' => ['text' => $text]] + $order)['order']['company'], null, 'при других способах оплаты реквизиты не сохраняем');
    eq(Checkout::companyTitle(['text' => $text]), 'ООО «Ромашка»');
});

group('письмо (SMTP)');
test('тема и имя по-русски кодируются, текст и HTML в base64', function () {
    $m = Smtp::message('shop@artflora.by', 'Артфлора — заказы', ['a@b.by'], 'Заказ №1', '<p>Привет</p>', "Строка\nвторая");
    contains($m, 'Subject: =?UTF-8?B?' . base64_encode('Заказ №1') . '?=');
    contains($m, 'From: =?UTF-8?B?' . base64_encode('Артфлора — заказы') . '?= <shop@artflora.by>');
    contains($m, 'multipart/alternative');
    contains($m, base64_encode("Строка\r\nвторая"));
    contains($m, base64_encode('<p>Привет</p>'));
});
test('строка с точкой в начале удваивается', function () {
    eq(Smtp::dotStuff(".a\nb\n.c"), "..a\nb\n..c");
});

group('Telegram');
test('текст уведомления', function () {
    $t = Notify::telegramText(['id' => 7, 'number' => 10007, 'totalKop' => 12000, 'items' => [['name' => 'Розы', 'size' => 'M', 'qty' => 2]],
        'deliveryMethod' => 'DELIVERY', 'addressUnknown' => false, 'address' => 'Уручье: пр. Независимости, 180', 'deliveryZone' => 1,
        'recipientPhone' => '', 'deliveryDate' => '2026-10-24', 'deliverySlot' => '14–16', 'customerName' => 'Анна', 'customerPhone' => '+375291112233', 'shop' => 'uruchye']);
    contains($t, '🌸 Новый заказ №10007 — 120 BYN');
    contains($t, '• Розы, M × 2');
    contains($t, 'Доставка: Уручье: пр. Независимости, 180 (зона 1)');
    contains($t, 'Когда: 2026-10-24, 14–16');
    contains($t, '/admin/orders/7');
    $t = Notify::telegramText(['company' => ['text' => "ООО «Ромашка»\nУНП 190000001"]] + ['id' => 7, 'number' => 10007, 'totalKop' => 12000, 'items' => [],
        'deliveryMethod' => 'PICKUP', 'addressUnknown' => false, 'address' => '', 'deliveryZone' => null, 'recipientPhone' => '',
        'deliveryDate' => '2026-10-24', 'deliverySlot' => '14–16', 'customerName' => 'Анна', 'customerPhone' => '+375291112233', 'shop' => 'uruchye']);
    contains($t, "Безнал, реквизиты для счёта:\nООО «Ромашка»\nУНП 190000001");
});
test('куда везти из браузера: только понятные значения', function () {
    eq(Checkout::parsePlace(['type' => 'point', 'lat' => 54.2060001, 'lng' => 27.853]), ['type' => 'point', 'lat' => 54.206, 'lng' => 27.853]);
    eq(Checkout::parsePlace(['type' => 'point', 'lat' => '54.2', 'lng' => 27.8]), null, 'строка вместо числа');
    eq(Checkout::parsePlace(['type' => 'point', 'lat' => 10, 'lng' => 27.8]), null, 'не Беларусь');
    eq(Checkout::parsePlace(['type' => 'zone', 'zone' => 2]), ['type' => 'zone', 'zone' => 2]);
    eq(Checkout::parsePlace(['type' => 'zone', 'zone' => '2']), null);
    eq(Checkout::parsePlace(['type' => 'area', 'name' => 'Удалённая доставка']), ['type' => 'area', 'name' => 'Удалённая доставка']);
    eq(Checkout::parsePlace(['type' => 'other']), null);
    eq(Checkout::parsePlace(null, ''), null);
});

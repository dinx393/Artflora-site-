<?php
group('письмо о заказе');
$base = [
    'number' => 5552, 'createdAt' => strtotime('2026-10-17T06:16:00Z'), 'status' => 'Новый', 'paid' => false, 'payment' => 'Картой (ссылкой)',
    'customerName' => 'Анастасия <b>', 'customerPhone' => '+375291112233', 'recipientName' => 'Галина Федоровна', 'recipientPhone' => '+375293874937',
    'callRecipient' => false, 'anonymous' => true, 'method' => 'DELIVERY', 'address' => 'Притыцкого 39, кв 64', 'addressUnknown' => false,
    'shopName' => 'Уручье', 'deliveryDate' => '2026-10-17', 'deliverySlot' => '14–16', 'comment' => 'Как можно быстрее', 'cardText' => '',
    'items' => [['name' => 'Композиция «Розовое облако»', 'size' => 'S', 'qty' => 1, 'priceKop' => 15000, 'photo' => 'https://artflora.by/a.jpg', 'url' => 'https://artflora.by/bouquet/rozovoe-oblako']],
    'itemsKop' => 15000, 'discountKop' => 0, 'deliveryKop' => 0, 'totalKop' => 15000, 'adminUrl' => 'https://artflora.by/admin/orders/1',
];
test('тема и главное в тексте', function () use ($base) {
    $m = OrderEmail::render($base);
    eq($m['subject'], 'Заказ №5552 на 150 BYN — ждёт обработки');
    contains($m['text'], 'Адрес доставки: Притыцкого 39, кв 64');
    contains($m['text'], 'Телефон получателя: +375 29 387-49-37');
    contains($m['text'], 'Дата заказа: 17.10.2026, 09:16');
    contains($m['text'], 'Когда: суббота, 17 октября, 14–16');
    contains($m['text'], 'не говорить, от кого букет');
    contains($m['html'], 'https://artflora.by/a.jpg');
    contains($m['html'], 'бесплатно');
    contains($m['text'], 'В подарок положить: Открытка и 2 пакетика подкормки (кризал)');
    contains($m['html'], 'В подарок: открытка и 2 пакетика подкормки (кризал)');
});
test('фото и название букета — ссылки на его страницу', function () use ($base) {
    $m = OrderEmail::render($base);
    contains($m['html'], '<a href="https://artflora.by/bouquet/rozovoe-oblako" style="display:block"><img');
    contains($m['html'], '>Композиция «Розовое облако»</a>');
    contains($m['text'], "\n  https://artflora.by/bouquet/rozovoe-oblako");
    $items = [['url' => null] + $base['items'][0]];
    ok(!str_contains(OrderEmail::render(['items' => $items] + $base)['html'], '/bouquet/'));
});
test('данные клиента экранируются', function () use ($base) {
    $m = OrderEmail::render($base);
    contains($m['html'], 'Анастасия &lt;b&gt;');
    ok(!str_contains($m['html'], 'Анастасия <b>'));
});
test('адрес неизвестен и самовывоз', function () use ($base) {
    contains(OrderEmail::render(['addressUnknown' => true, 'deliverySlot' => ''] + $base)['text'], 'Адрес и время уточнить у получателя');
    $p = OrderEmail::render(['method' => 'PICKUP', 'shopName' => 'Малиновка, ул. Яна Чечота 7', 'discountKop' => 1500, 'totalKop' => 13500] + $base);
    contains($p['text'], 'Самовывоз: Малиновка, ул. Яна Чечота 7');
    ok(!str_contains($p['text'], 'Получатель'));
    contains($p['subject'], '135 BYN');
});
test('безнал: реквизиты организации в письме', function () use ($base) {
    $m = OrderEmail::render(['payment' => 'Безнал (счёт организации)', 'company' => ['text' => "ООО «Ромашка»\nУНП 190000001"]] + $base);
    contains($m['text'], "Реквизиты для счёта: ООО «Ромашка»\nУНП 190000001");
    contains($m['html'], 'ООО «Ромашка»<br>УНП 190000001');
});

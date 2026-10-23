<?php
group('письмо о заказе');
$base = [
    'number' => 5552, 'createdAt' => strtotime('2026-10-17T06:16:00Z'), 'status' => 'Новый', 'paid' => false, 'payment' => 'Картой (ссылкой)',
    'customerName' => 'Анастасия <b>', 'customerPhone' => '+375291112233', 'recipientName' => 'Галина Федоровна', 'recipientPhone' => '+375293874937',
    'callRecipient' => false, 'anonymous' => true, 'method' => 'DELIVERY', 'address' => 'Притыцкого 39, кв 64', 'addressUnknown' => false,
    'shopName' => 'Уручье', 'deliveryDate' => '2026-10-17', 'deliverySlot' => '14–16', 'comment' => 'Как можно быстрее', 'cardText' => '',
    'items' => [['name' => 'Композиция «Розовое облако»', 'size' => 'S', 'qty' => 1, 'priceKop' => 15000, 'photo' => 'https://artflora.by/a.jpg']],
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

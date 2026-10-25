<?php
// Корзина, расчёт доставки, оформление заказа, страница «Заказ принят».
// Повторяет src/app/(shop)/cart, src/app/api/cart, src/app/api/orders, src/app/(shop)/order/[number].

Http::get('/cart', function () {
    $shop = Settings::get('shop');
    $delivery = Settings::get('delivery');
    return View::page('shop/cart', [
        'title' => 'Корзина',
        'noindex' => true,
        'shops' => array_map(fn ($s) => ['code' => $s['code'], 'name' => $s['name'], 'address' => $s['address']], $delivery['shops']),
        'pickupPct' => $delivery['pickupDiscountPct'],
        'freeFromKop' => $delivery['freeFromKop'],
        'districts' => Delivery::districtOptions(Settings::get('districts'), $delivery),
        'leadHours' => $shop['leadHours'],
        'delivery' => $delivery,
        'scripts' => '<script src="' . View::asset('js/checkout.js') . '"></script>' . ShopView::zonesMapScripts(),
    ]);
});

// Запрос с нашего сайта: JSON из fetch и тот же адрес в Origin (чужая страница так не сможет).
function cart_api_body(): mixed
{
    $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
    if ($origin !== '' && parse_url($origin, PHP_URL_HOST) !== parse_url('//' . ($_SERVER['HTTP_HOST'] ?? ''), PHP_URL_HOST)) {
        Http::json(['ok' => false, 'error' => 'bad-origin'], 403);
    }
    if (!str_starts_with(strtolower($_SERVER['CONTENT_TYPE'] ?? ''), 'application/json')) Http::json(['ok' => false, 'error' => 'bad-request'], 400);
    return Http::jsonBody();
}

// Корзина по актуальным ценам: браузер присылает только id и размеры.
// Куда везти — место на карте или кнопка зоны (Checkout::parsePlace); цену считаем здесь.
Http::post('/api/cart', function () {
    $raw = cart_api_body();
    $cart = Checkout::parseCart($raw['cart'] ?? null);
    $method = $raw['method'] ?? 'DELIVERY';
    if ($cart === null || !in_array($method, ['DELIVERY', 'PICKUP'], true)) Http::json(['error' => 'bad-request'], 400);
    $place = $method === 'DELIVERY' ? Checkout::parsePlace($raw['place'] ?? null, $raw['district'] ?? '') : null;
    $t = Checkout::totals($cart, $method, $place);
    if ($t['quote']) {
        $t['quote']['shopName'] = $t['quote']['shop']['name'] ?? null;
        unset($t['quote']['shop']); // браузеру координаты магазина не нужны
    }
    Http::json($t);
});

Http::post('/api/orders', function () {
    $raw = cart_api_body();
    if (!RateLimit::allow('order', RateLimit::clientIp())) {
        Http::json(['ok' => false, 'error' => 'Слишком много заказов подряд. Позвоните нам, пожалуйста'], 429);
    }
    $r = Checkout::createOrder($raw);
    if (!$r['ok']) Http::json($r, 422);
    $out = ['ok' => true, 'number' => $r['number'], 'token' => $r['token']];
    if (!$r['new']) Http::json($out, 201);
    Notify::respondJsonThen($out, 201, fn () => Notify::newOrder($r['id']));
});

Http::get('/order/{number}', function (string $number) {
    $t = (string) ($_GET['t'] ?? '');
    if (!ctype_digit($number) || !hash_equals(Auth::orderToken((int) $number), $t)) Http::abort(404);
    $order = Checkout::order('number = ?', [(int) $number]) ?? Http::abort(404);
    $pickupShop = null;
    foreach (Settings::get('delivery')['shops'] as $s) if ($s['code'] === $order['shop']) $pickupShop = $s;
    return View::page('shop/order', ['title' => 'Заказ принят', 'noindex' => true, 'order' => $order, 'shop' => Settings::get('shop'), 'pickupShop' => $pickupShop]);
});

<?php
group('ограничение частоты (база)');
Db::migrate();
test('вход: 5 попыток с одного адреса за 15 минут, потом стоп', function () {
    RateLimit::reset();
    $now = 1_000_000;
    for ($i = 0; $i < 5; $i++) eq(RateLimit::allow('login', '1.1.1.1', $now + $i), true);
    eq(RateLimit::allow('login', '1.1.1.1', $now + 10), false);
    eq(RateLimit::allow('login', '2.2.2.2', $now + 10), true);
});
test('через 15 минут снова можно', function () {
    RateLimit::reset();
    $now = 1_000_000;
    for ($i = 0; $i < 5; $i++) RateLimit::allow('login', '1.1.1.1', $now);
    eq(RateLimit::allow('login', '1.1.1.1', $now + 15 * 60_000 + 1), true);
});
test('общий лимит держит, даже если адреса разные', function () {
    RateLimit::reset();
    $now = 1_000_000;
    for ($i = 0; $i < 30; $i++) eq(RateLimit::allow('login', "10.0.0.$i", $now), true);
    eq(RateLimit::allow('login', '10.0.1.1', $now), false);
});
test('удачный вход обнуляет счётчик адреса', function () {
    RateLimit::reset();
    $now = 1_000_000;
    for ($i = 0; $i < 4; $i++) RateLimit::allow('login', '1.1.1.1', $now);
    RateLimit::forget('login', '1.1.1.1');
    for ($i = 0; $i < 5; $i++) eq(RateLimit::allow('login', '1.1.1.1', $now), true);
    RateLimit::reset();
});
test('общий лимит не закрывает вход с устройства, где уже входили', function () {
    RateLimit::reset();
    $now = 1_000_000;
    for ($i = 0; $i < 30; $i++) RateLimit::allow('login', "10.0.0.$i", $now);
    eq(RateLimit::allow('login', '10.0.1.1', $now), false);
    for ($i = 0; $i < 5; $i++) eq(RateLimit::allow('login', '10.0.1.1', $now, 'dev1'), true);
    eq(RateLimit::allow('login', '10.0.1.1', $now, 'dev1'), false);
    RateLimit::reset();
});

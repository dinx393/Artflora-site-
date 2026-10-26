<?php
group('адрес посетителя');
test('заголовкам прокси не верим, если запрос пришёл не от nginx', function () {
    eq(RateLimit::clientIp(['REMOTE_ADDR' => '93.84.1.2', 'HTTP_X_REAL_IP' => '1.2.3.4', 'HTTP_X_FORWARDED_FOR' => '5.6.7.8']), '93.84.1.2');
});
test('от nginx на этом же сервере берём X-Real-IP', function () {
    eq(RateLimit::clientIp(['REMOTE_ADDR' => '127.0.0.1', 'HTTP_X_REAL_IP' => '93.84.1.2']), '93.84.1.2');
    eq(RateLimit::clientIp(['REMOTE_ADDR' => '87.232.64.141', 'SERVER_ADDR' => '87.232.64.141', 'HTTP_X_REAL_IP' => '93.84.1.2']), '93.84.1.2');
});
test('X-Forwarded-For: последний адрес не нашего прокси, мусор не принимаем', function () {
    eq(RateLimit::clientIp(['REMOTE_ADDR' => '10.0.0.5', 'HTTP_X_FORWARDED_FOR' => '6.6.6.6, 93.84.1.2, 10.0.0.7']), '93.84.1.2');
    eq(RateLimit::clientIp(['REMOTE_ADDR' => '10.0.0.5', 'HTTP_X_REAL_IP' => 'abc', 'HTTP_X_FORWARDED_FOR' => 'evil']), '10.0.0.5');
});

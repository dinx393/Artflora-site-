<?php
group('вход в админку (база)');
$adminCfg = Config::all();
$loginAs = function (string $password) use ($adminCfg): string {
    Config::override(array_merge($adminCfg, ['admin_password' => $password]));
    $_COOKIE = [];
    Auth::startSession();
    return $_COOKIE['artflora_admin'];
};
$check = function (string $cookie, string $password) use ($adminCfg): bool {
    Config::override(array_merge($adminCfg, ['admin_password' => $password]));
    $_COOKIE = ['artflora_admin' => $cookie];
    Auth::forgetCache();
    return Auth::isAdmin();
};
test('вход действует, подделанная cookie — нет', function () use ($loginAs, $check) {
    $c = $loginAs('пароль-владельца-1');
    eq($check($c, 'пароль-владельца-1'), true);
    eq($check($c . 'x', 'пароль-владельца-1'), false);
    eq($check((string) ((time() + 3600) * 1000) . '.' . Auth::sign('x'), 'пароль-владельца-1'), false);
});
test('после выхода скопированная cookie не работает', function () use ($loginAs, $check) {
    $c = $loginAs('пароль-владельца-1');
    $other = $loginAs('пароль-владельца-1');
    $_COOKIE = ['artflora_admin' => $c];
    Auth::endSession();
    eq($check($c, 'пароль-владельца-1'), false);
    eq($check($other, 'пароль-владельца-1'), true, 'второе устройство');
});
test('смена пароля и «выйти на всех устройствах» отменяют все входы', function () use ($loginAs, $check) {
    $c = $loginAs('пароль-владельца-1');
    eq($check($c, 'новый-пароль-2026'), false);
    $c = $loginAs('пароль-владельца-1');
    Auth::endAllSessions();
    eq($check($c, 'пароль-владельца-1'), false);
});
test('вход живёт сутки', function () use ($loginAs) {
    $loginAs('пароль-владельца-1');
    $exp = (int) Db::value('SELECT MAX(expiresAt) FROM admin_sessions') / 1000;
    ok(abs($exp - (time() + 86400)) < 5, 'срок 24 часа');
});
Config::override($adminCfg);
$_COOKIE = [];
Auth::forgetCache();

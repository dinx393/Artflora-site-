<?php
group('вход в админку (база)');
Db::migrate();
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
test('сотрудник: свой логин, только заказы и каталог', function () use ($adminCfg) {
    Config::override(array_merge($adminCfg, ['admin_password' => 'пароль-владельца-1']));
    Db::exec('DELETE FROM admin_users');
    $id = Db::insert('admin_users', ['login' => 'olga', 'name' => 'Ольга', 'passwordHash' => Auth::hashPassword('пароль-ольги-1'), 'role' => 'staff']);
    eq(Auth::login('olga', 'неверный-пароль'), null);
    eq(Auth::login('nobody', 'пароль-ольги-1'), null);
    eq(Auth::login('', 'пароль-ольги-1'), null, 'пароль сотрудника без логина');
    $u = Auth::login(' Olga ', 'пароль-ольги-1');
    ok($u !== null, 'вход по логину');
    $_COOKIE = [];
    Auth::startSession($u);
    $c = $_COOKIE['artflora_admin'];
    Auth::forgetCache();
    eq(Auth::user()['name'], 'Ольга');
    eq([Auth::can('orders'), Auth::can('catalog'), Auth::can('delivery'), Auth::can('settings'), Auth::can('users')], [true, true, false, false, false]);
    eq(Auth::home(), '/admin/bouquets');
    // Сменили пароль сотруднику — его вход больше не действует
    Db::update('admin_users', $id, ['passwordHash' => Auth::hashPassword('другой-пароль-2')]);
    $_COOKIE = ['artflora_admin' => $c];
    Auth::forgetCache();
    eq(Auth::isAdmin(), false);
    // Отключённый сотрудник войти не может
    Db::update('admin_users', $id, ['active' => 0]);
    eq(Auth::login('olga', 'другой-пароль-2'), null);
    Db::exec('DELETE FROM admin_users');
});
test('основной пароль — старший администратор, ему можно всё', function () use ($loginAs, $check) {
    $c = $loginAs('пароль-владельца-1');
    eq($check($c, 'пароль-владельца-1'), true);
    eq([Auth::can('delivery'), Auth::can('settings'), Auth::can('users')], [true, true, true]);
    eq(Auth::user()['role'], 'owner');
});
Config::override($adminCfg);
$_COOKIE = [];
Auth::forgetCache();

<?php
// Общие адреса: вход в админку. robots.txt и sitemap.xml — в seo.php. Остальные страницы — в shop.php, cart.php, admin.php.

Http::get('/admin', fn () => Http::redirect(Auth::isAdmin() ? Auth::home() : '/admin/login'));

Http::get('/admin/login', function () {
    if (Auth::isAdmin()) Http::redirect(Auth::home());
    return View::page('admin/login', ['title' => 'Вход в управление сайтом', 'error' => null], null);
});

// Вход без проверки CSRF: подделывать вход в чужую админку бессмысленно, а пароль защищён лимитом попыток.
Http::post('/admin/login', function () {
    $ip = RateLimit::clientIp();
    $login = is_string($_POST['login'] ?? null) ? mb_substr($_POST['login'], 0, 40) : '';
    $password = is_string($_POST['password'] ?? null) ? $_POST['password'] : '';
    // С устройства, где уже входили, — свой счётчик: бот, перебирающий пароли, не закроет вход владельцу
    $device = Auth::deviceKey();
    $error = null;
    if (!RateLimit::allow('login', $ip, null, $device)) {
        $error = 'Слишком много попыток. Подождите 15 минут';
    } elseif (!($user = Auth::login($login, $password))) {
        $error = trim($login) === '' ? 'Неверный пароль' : 'Неверный логин или пароль';
    } else {
        RateLimit::forget('login', $ip, $device);
        Auth::startSession($user);
        Http::redirect(Auth::home());
    }
    http_response_code(401);
    return View::page('admin/login', ['title' => 'Вход в управление сайтом', 'error' => $error, 'login' => $login], null);
});

Http::post('/admin/logout', function () {
    Auth::checkCsrf();
    isset($_POST['all']) ? Auth::endAllSessions() : Auth::endSession();
    Http::redirect('/admin/login');
});

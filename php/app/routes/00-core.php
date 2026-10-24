<?php
// Общие адреса: вход в админку. robots.txt и sitemap.xml — в seo.php. Остальные страницы — в shop.php, cart.php, admin.php.

Http::get('/admin', fn () => Http::redirect(Auth::isAdmin() ? '/admin/bouquets' : '/admin/login'));

Http::get('/admin/login', function () {
    if (Auth::isAdmin()) Http::redirect('/admin/bouquets');
    return View::page('admin/login', ['title' => 'Вход в управление сайтом', 'error' => null], null);
});

// Вход без проверки CSRF: подделывать вход в чужую админку бессмысленно, а пароль защищён лимитом попыток.
Http::post('/admin/login', function () {
    $ip = RateLimit::clientIp();
    $error = null;
    if (!RateLimit::allow('login', $ip)) {
        $error = 'Слишком много попыток. Подождите 15 минут';
    } elseif (!Auth::checkPassword((string) ($_POST['password'] ?? ''))) {
        $error = 'Неверный пароль';
    } else {
        RateLimit::forget('login', $ip);
        Auth::startSession();
        Http::redirect('/admin/bouquets');
    }
    http_response_code(401);
    return View::page('admin/login', ['title' => 'Вход в управление сайтом', 'error' => $error], null);
});

Http::post('/admin/logout', function () {
    Auth::checkCsrf();
    Auth::endSession();
    Http::redirect('/admin/login');
});

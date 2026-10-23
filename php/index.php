<?php
// Вход для всех страниц сайта: адрес разбирают маршруты из app/routes/*.php.
require __DIR__ . '/app/bootstrap.php';

// Встроенный сервер PHP (php -S localhost:8000 index.php) сам отдаёт файлы: картинки, стили, шрифты.
if (PHP_SAPI === 'cli-server' && is_file(__DIR__ . parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH))
    && !str_starts_with(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), '/app/')) {
    return false;
}

header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header("Content-Security-Policy: frame-ancestors 'none'; base-uri 'self'; form-action 'self'; object-src 'none'");
header('Referrer-Policy: strict-origin-when-cross-origin');
header('Permissions-Policy: camera=(), microphone=(), geolocation=()');

$debug = Config::isLocalUrl((string) Config::get('site_url'));
set_exception_handler(function (Throwable $e) use ($debug): void {
    app_log(get_class($e) . ': ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine() . "\n" . $e->getTraceAsString());
    if (!headers_sent()) http_response_code(500);
    echo $debug ? '<pre>' . e($e) . '</pre>' : '<h1>Что-то пошло не так</h1><p>Попробуйте обновить страницу или позвоните нам.</p>';
});

$problems = Config::problems(Config::all());
if ($problems) {
    http_response_code(503);
    app_log('Сайт не запущен, исправьте app/config.php: ' . implode('; ', $problems));
    echo '<h1>Сайт настраивается</h1>' . ($debug ? '<pre>' . e(implode("\n", $problems)) . '</pre>' : '');
    exit;
}

$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
// Новые таблицы базы (app/migrations) применяются сами при первом заходе после обновления.
Db::migrate();

foreach (glob(__DIR__ . '/app/routes/*.php') as $routes) require $routes;
Http::dispatch($_SERVER['REQUEST_METHOD'] ?? 'GET', $path);

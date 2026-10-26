<?php
// Общий запуск: подключает классы из app/lib, настройки и обработку ошибок.
// Подключается из index.php (сайт), cron.php (по расписанию) и tests/run.php (тесты).
declare(strict_types=1);

const APP_DIR = __DIR__;
define('PUBLIC_DIR', dirname(__DIR__));

mb_internal_encoding('UTF-8');
date_default_timezone_set('Europe/Minsk');

spl_autoload_register(function (string $class): void {
    $file = APP_DIR . "/lib/$class.php";
    if (is_file($file)) require $file;
});
require_once APP_DIR . '/lib/View.php'; // функция e() для шаблонов

// Ошибки не показываем посетителям, а пишем в журнал за день: app/storage/errors-2026-10-26.log
// (папка закрыта от браузера). Журналы старше 60 дней удаляет ежедневная уборка (Maintenance).
function app_log(string $message): void
{
    $dir = APP_DIR . '/storage';
    if (!is_dir($dir)) @mkdir($dir, 0750, true);
    @file_put_contents("$dir/errors-" . date('Y-m-d') . '.log', '[' . date('Y-m-d H:i:s') . "] $message\n", FILE_APPEND | LOCK_EX);
}

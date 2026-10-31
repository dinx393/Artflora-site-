<?php
// Задания по расписанию. В панели хостинга → «Планировщик (CRON)» добавить, раз в 5 минут:
//   php /путь/к/сайту/app/cron.php
// Каждый раз: букеты с вышедшим таймером «собран сегодня» скрываются с сайта (Catalog::hideExpired),
// повтор уведомлений о заказах (Telegram и письмо), которые не дошли с первого раза.
// Раз в сутки после 3:00: удаление заказов старше 3 лет, старых журналов, резервная копия (Maintenance).
require __DIR__ . '/bootstrap.php';
// На хостинге php в Shell-клиенте бывает не «cli», а «cgi» — пускаем любой запуск не из браузера
if (PHP_SAPI !== 'cli' && isset($_SERVER['REQUEST_METHOD'])) exit;
$argv = $_SERVER['argv'] ?? $argv ?? [];

// Не запускаем второй раз, пока не закончил первый (медленная почта)
@mkdir(APP_DIR . '/storage', 0750, true);
$lock = fopen(APP_DIR . '/storage/cron.lock', 'c');
if (!$lock || !flock($lock, LOCK_EX | LOCK_NB)) exit;

try {
    Db::migrate();
    $hidden = Catalog::hideExpired();
    if (in_array('-v', $argv, true)) echo "Скрыто по таймеру букетов: $hidden\n";
    if (Catalog::shuffleIfDue() && in_array('-v', $argv, true)) echo "Букеты без значков перемешаны (раз в сутки)\n";
    $n = Notify::retryPending();
    if (in_array('-v', $argv, true)) echo "Проверено заказов с недошедшими уведомлениями: $n\n";
    if (Maintenance::due()) Maintenance::daily(in_array('-v', $argv, true));
} catch (Throwable $e) {
    app_log('cron: ' . get_class($e) . ': ' . $e->getMessage());
    exit(1);
}

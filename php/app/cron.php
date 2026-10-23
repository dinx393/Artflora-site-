<?php
// Задания по расписанию. В панели хостинга → «Планировщик (CRON)» добавить, раз в 5 минут:
//   php /путь/к/сайту/app/cron.php
// Сейчас: повтор уведомлений о заказах (Telegram и письмо), которые не дошли с первого раза.
require __DIR__ . '/bootstrap.php';
if (PHP_SAPI !== 'cli') exit;

// Не запускаем второй раз, пока не закончил первый (медленная почта)
@mkdir(APP_DIR . '/storage', 0750, true);
$lock = fopen(APP_DIR . '/storage/cron.lock', 'c');
if (!$lock || !flock($lock, LOCK_EX | LOCK_NB)) exit;

try {
    Db::migrate();
    $n = Notify::retryPending();
    if (in_array('-v', $argv, true)) echo "Проверено заказов с недошедшими уведомлениями: $n\n";
} catch (Throwable $e) {
    app_log('cron: ' . get_class($e) . ': ' . $e->getMessage());
    exit(1);
}

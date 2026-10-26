<?php
// Ежедневная уборка (app/cron.php, раз в сутки после 3:00 ночи):
//  • заказы старше 3 лет удаляем — так обещает политика конфиденциальности (решение владельца 2026-10-26);
//  • журналы ошибок по дням (app/storage/errors-2026-10-26.log), старше LOG_DAYS дней удаляем;
//  • резервная копия базы и фото с проверкой восстановления (Backup).
final class Maintenance
{
    public const ORDER_YEARS = 3;
    public const LOG_DAYS = 60;

    // Пора ли сегодняшнюю уборку: после 3:00 и сегодня ещё не делали
    public static function due(?int $now = null): bool
    {
        $now ??= time();
        return (int) date('G', $now) >= 3 && !is_file(self::marker($now));
    }

    private static function marker(int $now): string
    {
        return APP_DIR . '/storage/daily-' . date('Y-m-d', $now);
    }

    public static function daily(bool $verbose = false): void
    {
        $now = time();
        // Отметку ставим сразу: если что-то упадёт, не повторяем каждые 5 минут, а пробуем завтра
        foreach (glob(APP_DIR . '/storage/daily-*') ?: [] as $old) @unlink($old);
        @touch(self::marker($now));
        $say = function (string $s) use ($verbose) { if ($verbose) echo "$s\n"; };

        $n = self::purgeOrders();
        $say("Удалено заказов старше " . self::ORDER_YEARS . " лет: $n");
        if ($n) app_log("Уборка: удалено заказов старше " . self::ORDER_YEARS . " лет: $n");
        $say('Удалено старых журналов: ' . self::pruneLogs($now));
        try {
            $b = Backup::run();
            $say("Резервная копия: {$b['file']}, строк {$b['rows']}, новых фото {$b['photosCopied']}, проверено таблиц {$b['checked']}");
        } catch (Throwable $e) {
            Backup::failed($e->getMessage());
            app_log('Резервная копия не получилась: ' . $e->getMessage());
            $say('Резервная копия не получилась: ' . $e->getMessage());
        }
    }

    // Строки заказа (order_items) удаляются вместе с заказом
    public static function purgeOrders(): int
    {
        return Db::exec('DELETE FROM orders WHERE createdAt < NOW() - INTERVAL ' . self::ORDER_YEARS . ' YEAR');
    }

    public static function pruneLogs(?int $now = null): int
    {
        $limit = date('Y-m-d', ($now ?? time()) - self::LOG_DAYS * 86400);
        $n = 0;
        foreach (glob(APP_DIR . '/storage/errors-*.log') ?: [] as $f) {
            if (preg_match('/errors-(\d{4}-\d{2}-\d{2})\.log$/', $f, $m) && $m[1] < $limit && @unlink($f)) $n++;
        }
        return $n;
    }
}

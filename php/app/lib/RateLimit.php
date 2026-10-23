<?php
// Ограничение частоты запросов (подбор пароля, ботовые заказы). Хранится в базе (таблица rate_hits):
// на хостинге каждый запрос — отдельный процесс, память между ними не живёт.
// Два счётчика: по адресу посетителя и общий — он держит, даже если бот подставляет разные адреса.
final class RateLimit
{
    public const RULES = [
        'login' => ['perIp' => 5, 'total' => 30, 'windowMs' => 15 * 60_000],
        'order' => ['perIp' => 10, 'total' => 200, 'windowMs' => 60 * 60_000],
        'remind' => ['perIp' => 5, 'total' => 100, 'windowMs' => 60 * 60_000],
    ];

    // true — можно, false — слишком часто. $now — миллисекунды (для тестов).
    public static function allow(string $action, string $ip, ?int $now = null): bool
    {
        $r = self::RULES[$action];
        $now ??= (int) (microtime(true) * 1000);
        $since = $now - $r['windowMs'];
        if (random_int(1, 50) === 1) Db::exec('DELETE FROM rate_hits WHERE at < ?', [$now - 86_400_000]); // чистим старое
        $ipKey = "$action:ip:$ip";
        $allKey = "$action:all";
        $counts = Db::one('SELECT SUM(bucket = ?) AS ip, SUM(bucket = ?) AS total FROM rate_hits WHERE bucket IN (?, ?) AND at > ?', [$ipKey, $allKey, $ipKey, $allKey, $since]);
        if ((int) $counts['ip'] >= $r['perIp'] || (int) $counts['total'] >= $r['total']) return false;
        Db::exec('INSERT INTO rate_hits (bucket, at) VALUES (?, ?), (?, ?)', [$ipKey, $now, $allKey, $now]);
        return true;
    }

    // Удачный вход не должен считаться попыткой подбора: обнуляем счётчик этого адреса.
    public static function forget(string $action, string $ip): void
    {
        Db::exec('DELETE FROM rate_hits WHERE bucket = ?', ["$action:ip:$ip"]);
    }

    // Адрес посетителя. На хостинге запрос приходит через nginx хостинга, он ставит X-Real-IP / X-Forwarded-For.
    public static function clientIp(): string
    {
        $xff = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? '';
        $first = trim(explode(',', $xff)[0] ?? '');
        return $_SERVER['HTTP_X_REAL_IP'] ?? ($first ?: ($_SERVER['REMOTE_ADDR'] ?? 'unknown'));
    }

    public static function reset(): void
    {
        Db::exec('DELETE FROM rate_hits');
    }
}

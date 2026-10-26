<?php
// Ограничение частоты запросов (подбор пароля, ботовые заказы). Хранится в базе (таблица rate_hits):
// на хостинге каждый запрос — отдельный процесс, память между ними не живёт.
// Два счётчика: по адресу посетителя и общий — он держит, даже если бот подставляет разные адреса.
final class RateLimit
{
    public const RULES = [
        'login' => ['perIp' => 5, 'total' => 30, 'windowMs' => 15 * 60_000],
        'order' => ['perIp' => 10, 'total' => 200, 'windowMs' => 60 * 60_000],
    ];

    // true — можно, false — слишком часто. $now — миллисекунды (для тестов).
    // $key — вместо адреса (вход с устройства, где уже входили: у него свой счётчик, общий не мешает).
    public static function allow(string $action, string $ip, ?int $now = null, ?string $key = null): bool
    {
        $r = self::RULES[$action];
        $now ??= (int) (microtime(true) * 1000);
        $since = $now - $r['windowMs'];
        if (random_int(1, 50) === 1) Db::exec('DELETE FROM rate_hits WHERE at < ?', [$now - 86_400_000]); // чистим старое
        $ipKey = $key !== null ? "$action:key:$key" : "$action:ip:$ip";
        $allKey = $key !== null ? null : "$action:all";
        // Проверка и запись под замком: два одновременных запроса не проскочат оба на последней попытке
        $lock = "artflora-rate:$action";
        if (!Db::value('SELECT GET_LOCK(?, 5)', [$lock])) return false;
        try {
            $counts = Db::one('SELECT SUM(bucket = ?) AS ip, SUM(bucket = ?) AS total FROM rate_hits WHERE bucket IN (?, ?) AND at > ?', [$ipKey, $allKey, $ipKey, $allKey, $since]);
            if ((int) $counts['ip'] >= $r['perIp'] || ($allKey !== null && (int) $counts['total'] >= $r['total'])) return false;
            Db::exec('INSERT INTO rate_hits (bucket, at) VALUES (?, ?)', [$ipKey, $now]);
            if ($allKey !== null) Db::exec('INSERT INTO rate_hits (bucket, at) VALUES (?, ?)', [$allKey, $now]);
            return true;
        } finally {
            Db::value('SELECT RELEASE_LOCK(?)', [$lock]);
        }
    }

    // Удачный вход не должен считаться попыткой подбора: обнуляем счётчик этого адреса.
    public static function forget(string $action, string $ip, ?string $key = null): void
    {
        Db::exec('DELETE FROM rate_hits WHERE bucket = ?', [$key !== null ? "$action:key:$key" : "$action:ip:$ip"]);
    }

    // Адрес посетителя. На хостинге запрос приходит через nginx хостинга, он ставит X-Real-IP / X-Forwarded-For.
    // Этим заголовкам верим, только если запрос пришёл от самого nginx (этот же сервер, внутренняя сеть
    // или адрес из trusted_proxies в config.php). Иначе любой подставит в них чужой адрес и обойдёт лимит.
    public static function clientIp(?array $server = null): string
    {
        $server ??= $_SERVER;
        $remote = self::ip($server['REMOTE_ADDR'] ?? '') ?? 'unknown';
        if (!self::trustedProxy($remote, $server)) return $remote;
        $real = self::ip($server['HTTP_X_REAL_IP'] ?? '');
        if ($real !== null) return $real;
        // X-Forwarded-For: «клиент, прокси1, прокси2». Идём справа и берём первый адрес не нашего прокси.
        $chain = array_reverse(array_map('trim', explode(',', (string) ($server['HTTP_X_FORWARDED_FOR'] ?? ''))));
        foreach ($chain as $part) {
            $ip = self::ip($part);
            if ($ip === null) break;
            if (!self::trustedProxy($ip, $server)) return $ip;
        }
        return $remote;
    }

    private static function ip(string $s): ?string
    {
        $s = trim($s);
        return filter_var($s, FILTER_VALIDATE_IP) !== false ? $s : null;
    }

    private static function trustedProxy(string $ip, array $server): bool
    {
        if ($ip === 'unknown') return false;
        $list = Config::get('trusted_proxies') ?? [];
        if (is_string($list)) $list = array_filter(array_map('trim', explode(',', $list)));
        if (in_array($ip, (array) $list, true) || $ip === ($server['SERVER_ADDR'] ?? '')) return true;
        // Свой компьютер и внутренняя сеть хостинга (127.*, ::1, 10.*, 192.168.*, 172.16–31.*)
        return filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false;
    }

    public static function reset(): void
    {
        Db::exec('DELETE FROM rate_hits');
    }
}

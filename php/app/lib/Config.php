<?php
// Настройки из app/config.php. Для разработки в Docker их можно задать переменными окружения
// ARTFLORA_DB_HOST, ARTFLORA_ADMIN_PASSWORD и т.д. (имя ключа большими буквами).
final class Config
{
    private static ?array $values = null;

    private const DEFAULTS = [
        'db_host' => 'localhost', 'db_name' => '', 'db_user' => '', 'db_pass' => '',
        'site_url' => '', 'admin_password' => '', 'session_secret' => '',
        'smtp_host' => '', 'smtp_port' => 465, 'smtp_user' => '', 'smtp_pass' => '', 'smtp_from' => '',
        'telegram_bot_token' => '', 'telegram_chat_id' => '',
        'upload_dir' => '', // по умолчанию папка uploads рядом с index.php
        'trusted_proxies' => [], // адреса nginx хостинга, если он не на этом же сервере (обычно не нужно)
    ];

    // Пароли и ключи-заглушки: с ними сайт в интернете не запустится.
    private const KNOWN_DEFAULTS = ['artflora', 'local-only-secret-change-me-on-server', 'поменяйте-меня', 'замените-на-длинную-случайную-строку'];

    public static function all(): array
    {
        if (self::$values === null) {
            $file = APP_DIR . '/config.php';
            $v = array_merge(self::DEFAULTS, is_file($file) ? (require $file) : []);
            foreach (array_keys(self::DEFAULTS) as $k) {
                $env = getenv('ARTFLORA_' . strtoupper($k));
                if ($env !== false) $v[$k] = $env;
            }
            self::$values = $v;
        }
        return self::$values;
    }

    public static function get(string $key): mixed
    {
        return self::all()[$key] ?? null;
    }

    public static function siteUrl(): string
    {
        return rtrim((string) self::get('site_url'), '/');
    }

    // Только для тестов
    public static function override(array $values): void
    {
        self::$values = array_merge(self::DEFAULTS, $values);
    }

    // Сайт открыт только на этом компьютере или в домашней сети.
    public static function isLocalUrl(string $url): bool
    {
        $h = parse_url($url, PHP_URL_HOST);
        if (!$h) return false;
        return in_array($h, ['localhost', '127.0.0.1', '::1', '[::1]'], true) || str_ends_with($h, '.local')
            || preg_match('/^(10|192\.168|172\.(1[6-9]|2\d|3[01]))\./', $h);
    }

    // Что не так с настройками. На настоящем сервере сайт не стартует с паролем и ключом по умолчанию:
    // иначе любой, кто видел репозиторий, войдёт в админку.
    public static function problems(array $c): array
    {
        $site = (string) ($c['site_url'] ?? '');
        $secret = (string) ($c['session_secret'] ?? '');
        $password = (string) ($c['admin_password'] ?? '');
        $p = [];
        if (strlen($secret) < 16) $p[] = 'session_secret не задан или короче 16 символов';
        if (self::isLocalUrl($site)) return $p;
        // Ниже — только для сайта в интернете.
        if (!str_starts_with($site, 'https://')) $p[] = 'site_url должен начинаться с https:// (сейчас «' . ($site ?: 'пусто') . '»)';
        if (in_array($secret, self::KNOWN_DEFAULTS, true) || strlen($secret) < 32) $p[] = 'session_secret: нужна своя случайная строка от 32 символов';
        if (in_array($password, self::KNOWN_DEFAULTS, true) || mb_strlen($password) < 12) $p[] = 'admin_password: нужен свой пароль от 12 символов';
        return $p;
    }
}

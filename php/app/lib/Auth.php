<?php
// Простой вход в админку по паролю из config.php (admin_password).
// В cookie лежит случайный ключ входа, в базе (admin_sessions) — его отпечаток и срок. PHP-сессии не нужны.
// Выход удаляет вход в базе, смена admin_password отменяет все входы сразу.
final class Auth
{
    private const COOKIE = 'artflora_admin';
    private const HOURS = 24;
    // Устройство, с которого уже входили: общий лимит попыток (от ботов) его не блокирует
    private const DEVICE_COOKIE = 'artflora_device';
    private static ?bool $admin = null;

    private static function secret(): string
    {
        $s = (string) Config::get('session_secret');
        if (strlen($s) < 16) throw new RuntimeException('session_secret не задан или слишком короткий');
        return $s;
    }

    public static function sign(string $payload): string
    {
        return rtrim(strtr(base64_encode(hash_hmac('sha256', $payload, self::secret(), true)), '+/', '-_'), '=');
    }

    public static function checkPassword(string $password): bool
    {
        $expected = (string) Config::get('admin_password');
        if ($expected === '') return false;
        return hash_equals(self::sign($expected), self::sign($password));
    }

    private static function passwordMark(): string
    {
        return substr(self::sign('password:' . Config::get('admin_password')), 0, 22);
    }

    public static function startSession(): void
    {
        $token = rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
        $expires = time() + self::HOURS * 3600;
        Db::exec('DELETE FROM admin_sessions WHERE expiresAt < ?', [time() * 1000]);
        Db::insert('admin_sessions', [
            'id' => hash('sha256', $token), 'passwordMark' => self::passwordMark(), 'expiresAt' => $expires * 1000,
            'userAgent' => mb_substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 200),
        ]);
        self::setCookie(self::COOKIE, $token, $expires);
        self::setCookie(self::DEVICE_COOKIE, self::deviceId() ?? self::newDevice(), time() + 365 * 86400);
        self::$admin = true;
    }

    // Выйти: этот вход больше не действует, даже если cookie кто-то скопировал
    public static function endSession(): void
    {
        $v = $_COOKIE[self::COOKIE] ?? '';
        if (is_string($v) && $v !== '') Db::exec('DELETE FROM admin_sessions WHERE id = ?', [hash('sha256', $v)]);
        self::setCookie(self::COOKIE, '', time() - 3600);
        self::$admin = false;
    }

    // Выйти на всех устройствах (если телефон потерялся)
    public static function endAllSessions(): void
    {
        Db::exec('DELETE FROM admin_sessions');
        self::setCookie(self::COOKIE, '', time() - 3600);
        self::$admin = false;
    }

    private static function setCookie(string $name, string $value, int $expires): void
    {
        if (!headers_sent()) setcookie($name, $value, [
            'expires' => $expires,
            'path' => '/',
            'httponly' => true,
            'samesite' => 'Lax',
            // Только по https: на http://localhost иначе не войти.
            'secure' => str_starts_with((string) Config::get('site_url'), 'https://'),
        ]);
        $_COOKIE[$name] = $value;
    }

    public static function isAdmin(): bool
    {
        if (self::$admin !== null) return self::$admin;
        $v = $_COOKIE[self::COOKIE] ?? '';
        if (!is_string($v) || strlen($v) < 40) return self::$admin = false;
        $s = Db::one('SELECT passwordMark, expiresAt FROM admin_sessions WHERE id = ?', [hash('sha256', $v)]);
        return self::$admin = $s !== null && (int) $s['expiresAt'] > time() * 1000 && hash_equals(self::passwordMark(), $s['passwordMark']);
    }

    // Номер устройства из cookie, если подпись верна (сами придумать номер нельзя)
    public static function deviceId(): ?string
    {
        $v = $_COOKIE[self::DEVICE_COOKIE] ?? '';
        if (!is_string($v) || !str_contains($v, '.')) return null;
        [$id, $sig] = explode('.', $v, 2);
        return hash_equals(self::sign("device:$id"), $sig) ? $v : null;
    }

    private static function newDevice(): string
    {
        $id = bin2hex(random_bytes(12));
        return "$id." . self::sign("device:$id");
    }

    // Для лимита попыток входа: устройство, с которого уже входили, считается отдельно
    public static function deviceKey(): ?string
    {
        $d = self::deviceId();
        return $d === null ? null : explode('.', $d, 2)[0];
    }

    // Только для тестов
    public static function forgetCache(): void
    {
        self::$admin = null;
    }

    // Не вошли — на страницу входа
    public static function requireAdmin(): void
    {
        if (!self::isAdmin()) Http::redirect('/admin/login');
    }

    // Защита форм админки от подделки с чужих сайтов: скрытое поле с подписью текущего входа.
    public static function csrfToken(): string
    {
        return self::sign('csrf:' . ($_COOKIE[self::COOKIE] ?? ''));
    }

    public static function csrfField(): string
    {
        return '<input type="hidden" name="_csrf" value="' . View::e(self::csrfToken()) . '">';
    }

    // Для POST в админке: подпись из формы (или заголовка X-CSRF для fetch) и тот же сайт в Origin.
    public static function checkCsrf(): void
    {
        $token = $_POST['_csrf'] ?? ($_SERVER['HTTP_X_CSRF'] ?? '');
        $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
        $host = $_SERVER['HTTP_HOST'] ?? '';
        $sameOrigin = $origin === '' || parse_url($origin, PHP_URL_HOST) === parse_url("//$host", PHP_URL_HOST);
        if (!$sameOrigin || !is_string($token) || !hash_equals(self::csrfToken(), $token)) {
            Http::abort(403, 'Страница устарела. Обновите её и попробуйте ещё раз');
        }
    }

    // Ссылка на страницу «Заказ принят» содержит подпись, чтобы чужой заказ нельзя было открыть по номеру.
    public static function orderToken(int $number): string
    {
        return substr(self::sign("order:$number"), 0, 16);
    }
}

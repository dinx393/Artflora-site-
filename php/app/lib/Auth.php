<?php
// Простой вход в админку по паролю из config.php (admin_password).
// В cookie лежит срок действия, подписанный session_secret. PHP-сессии не нужны.
final class Auth
{
    private const COOKIE = 'artflora_admin';
    private const DAYS = 30;

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

    public static function startSession(): void
    {
        $exp = (string) ((time() + self::DAYS * 86400) * 1000);
        self::setCookie("$exp." . self::sign($exp), time() + self::DAYS * 86400);
    }

    public static function endSession(): void
    {
        self::setCookie('', time() - 3600);
    }

    private static function setCookie(string $value, int $expires): void
    {
        setcookie(self::COOKIE, $value, [
            'expires' => $expires,
            'path' => '/',
            'httponly' => true,
            'samesite' => 'Lax',
            // Только по https: на http://localhost иначе не войти.
            'secure' => str_starts_with((string) Config::get('site_url'), 'https://'),
        ]);
        $_COOKIE[self::COOKIE] = $value;
    }

    public static function isAdmin(): bool
    {
        $v = $_COOKIE[self::COOKIE] ?? '';
        if (!str_contains($v, '.')) return false;
        [$exp, $sig] = explode('.', $v, 2);
        if (!hash_equals(self::sign($exp), $sig)) return false;
        return (int) $exp > time() * 1000;
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

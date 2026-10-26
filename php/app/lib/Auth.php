<?php
// Вход в админку: паролем из config.php (admin_password, старший администратор, логин не нужен)
// или логином и паролем сотрудника (таблица admin_users, раздел «Сотрудники»).
// В cookie лежит случайный ключ входа, в базе (admin_sessions) — его отпечаток и срок. PHP-сессии не нужны.
// Выход удаляет вход в базе, смена пароля отменяет все входы с этим паролем.
final class Auth
{
    private const COOKIE = 'artflora_admin';
    private const HOURS = 24;
    // Устройство, с которого уже входили: общий лимит попыток (от ботов) его не блокирует
    private const DEVICE_COOKIE = 'artflora_device';
    // Разделы админки. Старший администратор (owner) может всё, обычный (staff) — только STAFF
    // (решение владельца 2026-10-26: заказы и весь каталог).
    public const ROLES = ['owner' => 'Старший администратор', 'staff' => 'Администратор'];
    public const STAFF = ['orders', 'catalog'];
    private static ?bool $admin = null;
    private static ?array $user = null;

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

    // Подпись пароля: пароль сменили — старые входы с ним недействительны
    private static function passwordMark(?array $user = null): string
    {
        $p = $user ? "user:{$user['id']}:{$user['passwordHash']}" : 'password:' . Config::get('admin_password');
        return substr(self::sign($p), 0, 22);
    }

    // Проверить логин и пароль. Логин пустой — пароль старшего администратора из config.php.
    // → сотрудник (строка admin_users) или ['id' => null] для пароля из config.php; не подошло — null
    public static function login(string $login, string $password): ?array
    {
        $login = mb_strtolower(trim($login));
        if ($login === '') return self::checkPassword($password) ? ['id' => null] : null;
        $u = Db::one('SELECT * FROM admin_users WHERE login = ? AND active = 1', [$login]);
        // Неизвестный логин проверяем так же долго, как известный: по времени ответа логины не угадать
        $ok = password_verify($password, $u['passwordHash'] ?? '$2y$10$' . str_repeat('x', 53));
        if (!$u || !$ok) return null;
        Db::exec('UPDATE admin_users SET lastLoginAt = NOW() WHERE id = ?', [$u['id']]);
        return $u;
    }

    public static function hashPassword(string $password): string
    {
        return password_hash($password, PASSWORD_DEFAULT);
    }

    public static function startSession(?array $user = null): void
    {
        $user = $user && $user['id'] !== null ? $user : null;
        $token = rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
        $expires = time() + self::HOURS * 3600;
        Db::exec('DELETE FROM admin_sessions WHERE expiresAt < ?', [time() * 1000]);
        Db::insert('admin_sessions', [
            'id' => hash('sha256', $token), 'userId' => $user['id'] ?? null, 'passwordMark' => self::passwordMark($user), 'expiresAt' => $expires * 1000,
            'userAgent' => mb_substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 200),
        ]);
        self::setCookie(self::COOKIE, $token, $expires);
        self::setCookie(self::DEVICE_COOKIE, self::deviceId() ?? self::newDevice(), time() + 365 * 86400);
        self::$admin = null;
        self::$user = null;
    }

    // Выйти: этот вход больше не действует, даже если cookie кто-то скопировал
    public static function endSession(): void
    {
        $v = $_COOKIE[self::COOKIE] ?? '';
        if (is_string($v) && $v !== '') Db::exec('DELETE FROM admin_sessions WHERE id = ?', [hash('sha256', $v)]);
        self::setCookie(self::COOKIE, '', time() - 3600);
        self::$admin = false;
        self::$user = null;
    }

    // Отменить все входы сотрудника (сменили пароль, роль, отключили)
    public static function endUserSessions(int $userId): void
    {
        Db::exec('DELETE FROM admin_sessions WHERE userId = ?', [$userId]);
    }

    // Выйти на всех устройствах (если телефон потерялся). Старший администратор выводит всех,
    // сотрудник — только себя.
    public static function endAllSessions(): void
    {
        $u = self::user();
        if ($u === null || $u['role'] === 'owner') Db::exec('DELETE FROM admin_sessions');
        else self::endUserSessions($u['id']);
        self::setCookie(self::COOKIE, '', time() - 3600);
        self::$admin = false;
        self::$user = null;
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
        return self::user() !== null;
    }

    // Кто вошёл: ['id' => null|int, 'name', 'login', 'role']; не вошли — null
    public static function user(): ?array
    {
        if (self::$admin !== null) return self::$user;
        self::$admin = false;
        $v = $_COOKIE[self::COOKIE] ?? '';
        if (!is_string($v) || strlen($v) < 40) return null;
        $s = Db::one('SELECT userId, passwordMark, expiresAt FROM admin_sessions WHERE id = ?', [hash('sha256', $v)]);
        if ($s === null || (int) $s['expiresAt'] <= time() * 1000) return null;
        if ($s['userId'] === null) {
            $user = ['id' => null, 'name' => 'Основной пароль', 'login' => '', 'role' => 'owner'];
            $mark = self::passwordMark();
        } else {
            $u = Db::one('SELECT * FROM admin_users WHERE id = ? AND active = 1', [$s['userId']]);
            if (!$u) return null;
            $user = ['id' => (int) $u['id'], 'name' => $u['name'], 'login' => $u['login'], 'role' => $u['role']];
            $mark = self::passwordMark($u);
        }
        if (!hash_equals($mark, $s['passwordMark'])) return null;
        self::$admin = true;
        return self::$user = $user;
    }

    // Можно ли в этот раздел: orders, catalog, delivery, settings, users
    public static function can(string $perm): bool
    {
        $u = self::user();
        return $u !== null && ($u['role'] === 'owner' || in_array($perm, self::STAFF, true));
    }

    // Первая доступная страница админки
    public static function home(): string
    {
        return self::can('catalog') ? '/admin/bouquets' : '/admin/orders';
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
        self::$user = null;
    }

    // Не вошли — на страницу входа
    // Не вошли — на страницу входа; раздел не разрешён — «Доступ запрещён»
    public static function requireAdmin(?string $perm = null): void
    {
        if (!self::isAdmin()) Http::redirect('/admin/login');
        if ($perm !== null && !self::can($perm)) Http::abort(403, 'Этот раздел доступен только старшему администратору.');
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

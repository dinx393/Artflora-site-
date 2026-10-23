<?php
// Маршруты и ответы. Страницы регистрируются в app/routes/*.php:
//   Http::get('/bouquet/{slug}', fn (string $slug) => View::page('shop/bouquet', [...]));
//   Http::post('/api/orders', fn () => Http::json([...]));
// Обработчик возвращает HTML строкой (или сам отвечает через json/redirect).
final class Http
{
    private static array $routes = [];

    public static function get(string $pattern, callable $handler): void
    {
        self::$routes[] = ['GET', $pattern, $handler];
    }

    public static function post(string $pattern, callable $handler): void
    {
        self::$routes[] = ['POST', $pattern, $handler];
    }

    public static function dispatch(string $method, string $path): void
    {
        $path = '/' . trim(rawurldecode($path), '/');
        $allowed = false;
        foreach (self::$routes as [$m, $pattern, $handler]) {
            $re = '#^' . preg_replace('#\\\{(\w+)\\\}#', '([^/]+)', preg_quote(rtrim($pattern, '/') ?: '/', '#')) . '$#u';
            if (!preg_match($re, $path, $args)) continue;
            if ($m !== $method && !($m === 'GET' && $method === 'HEAD')) { $allowed = true; continue; }
            $out = $handler(...array_slice($args, 1));
            if (is_string($out)) echo $out;
            return;
        }
        self::abort($allowed ? 405 : 404);
    }

    public static function redirect(string $to, int $code = 303): never
    {
        header("Location: $to", true, $code);
        exit;
    }

    public static function json(mixed $data, int $code = 200): never
    {
        http_response_code($code);
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    // Тело запроса JSON (fetch из браузера). Не JSON — null.
    public static function jsonBody(): mixed
    {
        $raw = file_get_contents('php://input');
        return $raw === '' ? null : json_decode($raw, true);
    }

    public static function abort(int $code, string $message = ''): never
    {
        http_response_code($code);
        $title = match ($code) { 404 => 'Страница не найдена', 403 => 'Доступ запрещён', 405 => 'Так нельзя', default => 'Что-то пошло не так' };
        echo View::page('error', ['title' => $title, 'code' => $code, 'message' => $message], $code === 404 ? 'shop' : null);
        exit;
    }

    public static function isPost(): bool
    {
        return ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';
    }

    // Строка из запроса (GET или POST), обрезанная по длине
    public static function str(string $key, int $max = 500): string
    {
        $v = $_POST[$key] ?? $_GET[$key] ?? '';
        return is_string($v) ? mb_substr(trim($v), 0, $max) : '';
    }

    // Текущий адрес с изменёнными параметрами: Http::url(['color' => 'red', 'page' => null])
    public static function url(array $changes, ?string $path = null): string
    {
        $q = array_filter(array_merge($_GET, $changes), fn ($v) => $v !== null && $v !== '');
        $path ??= strtok($_SERVER['REQUEST_URI'] ?? '/', '?');
        return $path . ($q ? '?' . http_build_query($q) : '');
    }
}

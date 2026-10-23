<?php
// Шаблоны страниц лежат в app/views/*.php. Внутри шаблона переменные из $data доступны по имени,
// а всё, что пришло от пользователя или из базы, выводим через View::e() (или короткую e()).
final class View
{
    public static function e(mixed $s): string
    {
        return htmlspecialchars((string) $s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    // Отрисовать кусок шаблона и вернуть строкой
    public static function render(string $template, array $data = []): string
    {
        $file = APP_DIR . "/views/$template.php";
        if (!is_file($file)) throw new RuntimeException("Нет шаблона $template");
        extract($data, EXTR_SKIP);
        ob_start();
        try {
            require $file;
        } catch (Throwable $e) {
            ob_end_clean();
            throw $e;
        }
        return ob_get_clean();
    }

    // Целая страница: шаблон внутри общего оформления.
    // $layout: 'shop' — шапка и подвал магазина, 'admin' — меню админки, null — без оформления.
    // В $data можно передать title (заголовок вкладки), description, noindex.
    public static function page(string $template, array $data = [], ?string $layout = 'shop'): string
    {
        $content = self::render($template, $data);
        if ($layout === null) return self::render('layouts/bare', $data + ['content' => $content]);
        return self::render("layouts/$layout", $data + ['content' => $content]);
    }

    // Адрес файла из assets с меткой версии: браузер подхватит новый файл после обновления сайта
    public static function asset(string $path): string
    {
        $file = PUBLIC_DIR . '/assets/' . $path;
        return '/assets/' . $path . (is_file($file) ? '?v=' . filemtime($file) : '');
    }
}

function e(mixed $s): string
{
    return View::e($s);
}

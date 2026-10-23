<?php
// Фото букетов храним в папке uploads рядом с index.php, отдаёт их сам веб-сервер по адресу /uploads/<файл>.
// В этой папке запрещено выполнять PHP (uploads/.htaccess), а имена файлов мы придумываем сами.
final class Uploads
{
    public const TYPES = ['image/jpeg' => '.jpg', 'image/png' => '.png', 'image/webp' => '.webp'];
    public const MAX_BYTES = 10 * 1024 * 1024;

    public static function dir(): string
    {
        return Config::get('upload_dir') ?: PUBLIC_DIR . '/uploads';
    }

    public static function safeName(string $name): ?string
    {
        return preg_match('/^[a-z0-9-]+\.(jpg|png|webp)$/', $name) ? $name : null;
    }

    // Проверяем сам файл, а не только его тип из браузера: первые байты должны быть настоящей картинкой.
    public static function looksLikeImage(string $buf, string $type): bool
    {
        return match ($type) {
            'image/jpeg' => str_starts_with($buf, "\xFF\xD8\xFF"),
            'image/png' => str_starts_with($buf, "\x89PNG\r\n\x1A\n"),
            'image/webp' => str_starts_with($buf, 'RIFF') && substr($buf, 8, 4) === 'WEBP',
            default => false,
        };
    }

    // Сохранить загруженный файл из $_FILES[...]: ['ok' => true, 'path' => '/uploads/…'] или ['ok' => false, 'error' => …]
    public static function save(array $file): array
    {
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) return ['ok' => false, 'error' => 'Файл не загрузился'];
        if ($file['size'] > self::MAX_BYTES) return ['ok' => false, 'error' => 'Фото больше 10 МБ'];
        $data = file_get_contents($file['tmp_name']);
        $type = null;
        foreach (array_keys(self::TYPES) as $t) if (self::looksLikeImage($data, $t)) $type = $t;
        if (!$type) return ['ok' => false, 'error' => 'Нужна картинка JPG, PNG или WebP'];
        $name = date('Ymd') . '-' . bin2hex(random_bytes(6)) . self::TYPES[$type];
        if (!is_dir(self::dir())) mkdir(self::dir(), 0755, true);
        file_put_contents(self::dir() . "/$name", $data);
        return ['ok' => true, 'path' => "/uploads/$name"];
    }
}

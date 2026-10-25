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

    // Адрес фото для покупателя с ?v=<время файла>: пересохранили фото — телефоны не покажут старое из кэша
    public static function url(string $path): string
    {
        $name = str_starts_with($path, '/uploads/') ? self::safeName(substr($path, 9)) : null;
        $file = $name ? self::dir() . "/$name" : null;
        return $file && is_file($file) ? "$path?v=" . filemtime($file) : $path;
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
        if ($type === 'image/jpeg') $data = self::shrinkJpeg($data);
        $name = date('Ymd') . '-' . bin2hex(random_bytes(6)) . self::TYPES[$type];
        if (!is_dir(self::dir())) mkdir(self::dir(), 0755, true);
        file_put_contents(self::dir() . "/$name", $data);
        return ['ok' => true, 'path' => "/uploads/$name"];
    }

    // Фото прямо с iPhone (3024×4032, 2–3 МБ) несут вторую, HDR-картинку («карту яркости»). iPhone 12 и новее
    // показывают такое фото в HDR, и на белой странице оно выглядит тусклым и затемнённым, а на iPhone 11 — нормально.
    // Пересохраняем: до 1600 px по длинной стороне, без HDR-части, с тем же цветовым профилем (Display P3).
    // Обычные небольшие JPEG не трогаем. Нет GD или фото не читается — оставляем как было.
    public const MAX_SIDE = 1600;

    public static function needsShrink(string $jpeg): bool
    {
        if (str_contains($jpeg, "MPF\0") || str_contains($jpeg, 'hdrgm') || str_contains($jpeg, 'GainMap')) return true;
        $size = @getimagesizefromstring($jpeg);
        return $size && max($size[0], $size[1]) > self::MAX_SIDE + 400;
    }

    public static function shrinkJpeg(string $jpeg): string
    {
        if (!function_exists('imagecreatefromstring') || !self::needsShrink($jpeg)) return $jpeg;
        // Фото 3024×4032 занимает в памяти ~60 МБ, а на хостинге лимит бывает 64–128 МБ — на всякий случай поднимаем
        $size = @getimagesizefromstring($jpeg);
        if (!$size) return $jpeg;
        $need = (int) ($size[0] * $size[1] * 5 * 2.2) + memory_get_usage() + 16 * 1024 * 1024;
        if (self::bytes(ini_get('memory_limit')) < $need) @ini_set('memory_limit', (string) max($need, 256 * 1024 * 1024));
        $img = @imagecreatefromstring($jpeg);
        if (!$img) return $jpeg;
        $angle = [3 => 180, 6 => -90, 8 => 90][self::orientation($jpeg)] ?? 0;
        $w = imagesx($img);
        $h = imagesy($img);
        $k = min(1, self::MAX_SIDE / max($w, $h));
        if ($k < 1) {
            // imagecopyresampled, а не imagescale: на хостинге imagescale вернул false
            $nw = (int) round($w * $k);
            $nh = (int) round($h * $k);
            $small = @imagecreatetruecolor($nw, $nh);
            if (!$small || !@imagecopyresampled($small, $img, 0, 0, 0, 0, $nw, $nh, $w, $h)) return $jpeg;
            imagedestroy($img);
            $img = $small;
        }
        if ($angle) $img = @imagerotate($img, $angle, 0);
        if (!$img) return $jpeg;
        ob_start();
        $okJpeg = @imagejpeg($img, null, 85);
        $out = ob_get_clean();
        // GD теряет цветовой профиль — возвращаем его сразу после начала файла
        return $okJpeg && $out ? "\xFF\xD8" . self::iccSegments($jpeg) . substr($out, 2) : $jpeg;
    }

    // «128M» → байты; -1 — без лимита
    public static function bytes(string|false $v): int
    {
        $v = trim((string) $v);
        if ($v === '' || $v === '-1') return PHP_INT_MAX;
        $n = (int) $v;
        return match (strtolower(substr($v, -1))) { 'g' => $n << 30, 'm' => $n << 20, 'k' => $n << 10, default => $n };
    }

    // Пересохранить уже загруженные фото (при обновлении сайта). Имена файлов не меняются. Возвращает число исправленных.
    public static function shrinkAll(bool $verbose = false): int
    {
        if (!function_exists('imagecreatefromstring')) {
            if ($verbose) echo "В PHP на хостинге нет модуля GD — фото пересохранить нечем\n";
            return 0;
        }
        $n = 0;
        foreach (glob(self::dir() . '/*.jpg') ?: [] as $file) {
            $data = (string) file_get_contents($file);
            if (!self::needsShrink($data)) continue;
            if ($verbose) echo basename($file) . ' … ';
            $new = self::shrinkJpeg($data);
            $done = $new !== $data && file_put_contents("$file.tmp", $new) && rename("$file.tmp", $file);
            if ($done) $n++;
            if ($verbose) echo $done ? round(strlen($data) / 1048576, 1) . ' МБ → ' . round(strlen($new) / 1024) . " КБ\n" : "не получилось (память: " . ini_get('memory_limit') . ")\n";
        }
        return $n;
    }

    // Поворот из EXIF (1 — как есть). GD сам его не учитывает, а браузеры учитывают.
    public static function orientation(string $jpeg): int
    {
        if (!function_exists('exif_read_data')) return 1;
        $exif = @exif_read_data('data://image/jpeg;base64,' . base64_encode($jpeg));
        return (int) ($exif['Orientation'] ?? 1);
    }

    // Сегменты APP2 «ICC_PROFILE» из исходного JPEG, как есть
    public static function iccSegments(string $jpeg): string
    {
        $out = '';
        $pos = 2;
        $len = strlen($jpeg);
        while ($pos + 4 <= $len && $jpeg[$pos] === "\xFF") {
            $marker = ord($jpeg[$pos + 1]);
            if ($marker === 0xDA || $marker === 0xD9) break;
            $size = unpack('n', substr($jpeg, $pos + 2, 2))[1];
            if ($marker === 0xE2 && substr($jpeg, $pos + 4, 12) === "ICC_PROFILE\0") $out .= substr($jpeg, $pos, $size + 2);
            $pos += $size + 2;
        }
        return $out;
    }
}

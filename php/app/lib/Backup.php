<?php
// Резервные копии (решение владельца 2026-10-26): каждый день база целиком в файл .sql.gz и копия фото.
// Лежат вне папки сайта (браузер их не откроет): ~/backups/<сайт>/ — db-2026-10-26.sql.gz и uploads/.
// После каждой копии — проверка восстановления: копия загружается в базу во временные таблицы
// и число строк сверяется с настоящими. Запускает app/cron.php раз в сутки после 3:00,
// вручную — php app/cli.php backup; вернуть базу из копии — php app/cli.php restore <файл>.
final class Backup
{
    public const KEEP_DAYS = 14;
    private const CHECK_PREFIX = 'zz_check_';

    public static function dir(): string
    {
        $d = (string) Config::get('backup_dir');
        return rtrim($d !== '' ? $d : dirname(PUBLIC_DIR, 2) . '/backups/' . basename(PUBLIC_DIR), '/');
    }

    // Сделать копию базы и фото, проверить её, удалить старые. → итог для журнала и админки
    public static function run(): array
    {
        $dir = self::dir();
        if (!is_dir($dir) && !@mkdir($dir, 0700, true)) throw new RuntimeException("не удалось создать папку $dir");
        $file = "$dir/db-" . date('Y-m-d') . '.sql.gz';
        $rows = self::dump($file);
        $photos = self::copyUploads("$dir/uploads");
        $check = self::check($file);
        $removed = self::prune($dir);
        $status = [
            'at' => date('Y-m-d H:i:s'), 'ok' => true, 'file' => $file, 'bytes' => filesize($file), 'rows' => array_sum($rows),
            'photosCopied' => $photos, 'checked' => $check, 'removedOld' => $removed,
        ];
        self::saveStatus($status);
        return $status;
    }

    public static function tables(): array
    {
        $names = array_map(fn ($r) => (string) array_values($r)[0], Db::all("SHOW FULL TABLES WHERE Table_type = 'BASE TABLE'"));
        return array_values(array_filter($names, fn ($t) => !str_starts_with($t, self::CHECK_PREFIX)));
    }

    // Итог последней копии (для админки → «Настройки»)
    public static function status(): ?array
    {
        $f = APP_DIR . '/storage/backup-status.json';
        return is_file($f) ? json_decode((string) file_get_contents($f), true) : null;
    }

    private static function saveStatus(array $s): void
    {
        @mkdir(APP_DIR . '/storage', 0750, true);
        file_put_contents(APP_DIR . '/storage/backup-status.json', json_encode($s, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));
    }

    // Копия не получилась — запоминаем, чтобы показать в админке
    public static function failed(string $error): void
    {
        $prev = self::status();
        $lastOk = ($prev['ok'] ?? false) ? $prev['at'] : ($prev['lastOk'] ?? null);
        self::saveStatus(['at' => date('Y-m-d H:i:s'), 'ok' => false, 'error' => $error, 'lastOk' => $lastOk]);
    }

    // База → файл. Каждая строка таблицы — своя строка файла (переводы строк в тексте экранированы),
    // в конце — сколько строк в каждой таблице: по ним проверяем копию. → [таблица => строк]
    public static function dump(string $file): array
    {
        $tmp = "$file.part";
        $gz = gzopen($tmp, 'wb6') ?: throw new RuntimeException("не удалось записать $tmp");
        $pdo = Db::pdo();
        $counts = [];
        gzwrite($gz, '-- Artflora.by: резервная копия базы ' . date('Y-m-d H:i:s') . "\nSET NAMES utf8mb4;\nSET FOREIGN_KEY_CHECKS = 0;\n");
        foreach (self::tables() as $t) {
            $create = array_values(Db::one("SHOW CREATE TABLE `$t`"))[1];
            gzwrite($gz, "\nDROP TABLE IF EXISTS `$t`;\n$create;\n");
            $st = $pdo->query("SELECT * FROM `$t`", PDO::FETCH_NUM);
            $n = 0;
            while ($row = $st->fetch(PDO::FETCH_NUM)) {
                $vals = array_map(fn ($v) => $v === null ? 'NULL' : (is_int($v) || is_float($v) ? (string) $v : $pdo->quote((string) $v)), $row);
                gzwrite($gz, ($n % 200 === 0 ? ($n ? ";\n" : '') . "INSERT INTO `$t` VALUES\n" : ",\n") . '(' . implode(',', $vals) . ')');
                $n++;
            }
            if ($n) gzwrite($gz, ";\n");
            $counts[$t] = $n;
        }
        gzwrite($gz, "\nSET FOREIGN_KEY_CHECKS = 1;\n");
        foreach ($counts as $t => $n) gzwrite($gz, "-- rows $t $n\n");
        gzwrite($gz, "-- end\n");
        gzclose($gz);
        rename($tmp, $file);
        @chmod($file, 0600);
        return $counts;
    }

    // Запросы из файла копии по одному. $prefix — загрузить таблицы под другими именами (для проверки).
    private static function statements(string $file, string $prefix = ''): Generator
    {
        $gz = gzopen($file, 'rb') ?: throw new RuntimeException("не удалось открыть $file");
        $buf = '';
        while (($line = gzgets($gz)) !== false) {
            if ($buf === '' && (str_starts_with($line, '--') || trim($line) === '')) continue;
            $buf .= $line;
            if (!str_ends_with(rtrim($line, "\r\n"), ';')) continue;
            $sql = rtrim($buf, ";\r\n");
            $buf = '';
            if ($prefix !== '') {
                $sql = preg_replace('/^(DROP TABLE IF EXISTS|CREATE TABLE|INSERT INTO) `/', '$1 `' . $prefix, $sql);
                // Имена связей между таблицами общие на всю базу — у временной копии их не делаем
                $sql = preg_replace('/^\s*CONSTRAINT `[^`]+` FOREIGN KEY.*\n/m', '', $sql);
                $sql = preg_replace('/,(\s*\n\))/', '$1', $sql);
            }
            yield $sql;
        }
        gzclose($gz);
        if (trim($buf) !== '') throw new RuntimeException('копия обрывается на середине');
    }

    // Что записано в конце копии: [таблица => строк]; конца нет — копия неполная
    public static function expected(string $file): array
    {
        $gz = gzopen($file, 'rb') ?: throw new RuntimeException("не удалось открыть $file");
        $rows = [];
        $end = false;
        while (($line = gzgets($gz)) !== false) {
            if (preg_match('/^-- rows (\S+) (\d+)$/', rtrim($line), $m)) $rows[$m[1]] = (int) $m[2];
            if (rtrim($line) === '-- end') $end = true;
        }
        gzclose($gz);
        if (!$end) throw new RuntimeException('копия неполная (нет отметки конца)');
        return $rows;
    }

    // Проверка восстановления: загрузить копию во временные таблицы zz_check_*, сверить строки, удалить.
    // → сколько таблиц проверено; не сходится — исключение
    public static function check(string $file): int
    {
        $want = self::expected($file);
        try {
            foreach (self::statements($file, self::CHECK_PREFIX) as $sql) Db::pdo()->exec($sql);
            foreach ($want as $t => $n) {
                $got = (int) Db::value('SELECT COUNT(*) FROM `' . self::CHECK_PREFIX . $t . '`');
                if ($got !== $n) throw new RuntimeException("в таблице $t после восстановления $got строк вместо $n");
            }
        } finally {
            foreach (array_keys($want) as $t) Db::pdo()->exec('DROP TABLE IF EXISTS `' . self::CHECK_PREFIX . $t . '`');
        }
        return count($want);
    }

    // Вернуть базу из копии (все таблицы заменяются тем, что в файле)
    public static function restore(string $file): array
    {
        $want = self::expected($file);
        foreach (self::statements($file) as $sql) Db::pdo()->exec($sql);
        Db::pdo()->exec('SET FOREIGN_KEY_CHECKS = 1');
        return $want;
    }

    // Фото: докладываем новые и изменённые файлы. → сколько скопировано
    public static function copyUploads(string $to): int
    {
        $from = Uploads::dir();
        if (!is_dir($from)) return 0;
        $n = 0;
        $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($from, FilesystemIterator::SKIP_DOTS));
        foreach ($it as $f) {
            if (!$f->isFile()) continue;
            $rel = substr($f->getPathname(), strlen($from) + 1);
            $dest = "$to/$rel";
            if (is_file($dest) && filesize($dest) === $f->getSize() && filemtime($dest) >= $f->getMTime()) continue;
            if (!is_dir(dirname($dest))) mkdir(dirname($dest), 0700, true);
            if (copy($f->getPathname(), $dest)) $n++;
        }
        return $n;
    }

    // Копии базы старше KEEP_DAYS дней удаляем. → сколько удалено
    public static function prune(string $dir, ?int $now = null): int
    {
        $limit = date('Y-m-d', ($now ?? time()) - self::KEEP_DAYS * 86400);
        $n = 0;
        foreach (glob("$dir/db-*.sql.gz") ?: [] as $f) {
            if (preg_match('/db-(\d{4}-\d{2}-\d{2})\.sql\.gz$/', $f, $m) && $m[1] < $limit && @unlink($f)) $n++;
        }
        return $n;
    }
}

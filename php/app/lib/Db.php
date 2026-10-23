<?php
// Подключение к MySQL и короткие помощники для запросов.
// Всегда передаём значения параметрами (?), а не вклеиваем в текст запроса.
final class Db
{
    private static ?PDO $pdo = null;

    public static function pdo(): PDO
    {
        if (self::$pdo === null) {
            $c = Config::all();
            self::$pdo = new PDO(
                "mysql:host={$c['db_host']};dbname={$c['db_name']};charset=utf8mb4",
                $c['db_user'],
                $c['db_pass'],
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                    PDO::ATTR_STRINGIFY_FETCHES => false,
                ],
            );
            // Время в базе — минское: так createdAt совпадает с тем, что видит магазин.
            self::$pdo->exec("SET time_zone = '+03:00'");
        }
        return self::$pdo;
    }

    public static function query(string $sql, array $params = []): PDOStatement
    {
        $st = self::pdo()->prepare($sql);
        $st->execute($params);
        return $st;
    }

    public static function all(string $sql, array $params = []): array
    {
        return self::query($sql, $params)->fetchAll();
    }

    public static function one(string $sql, array $params = []): ?array
    {
        $row = self::query($sql, $params)->fetch();
        return $row === false ? null : $row;
    }

    public static function value(string $sql, array $params = []): mixed
    {
        $v = self::query($sql, $params)->fetchColumn();
        return $v === false ? null : $v;
    }

    // Выполнить запрос, вернуть число затронутых строк.
    public static function exec(string $sql, array $params = []): int
    {
        return self::query($sql, $params)->rowCount();
    }

    // Вставить строку: Db::insert('flowers', ['name' => 'Роза', 'priceKop' => 1000]) → новый id
    public static function insert(string $table, array $row): int
    {
        $cols = implode(', ', array_map(fn ($c) => "`$c`", array_keys($row)));
        $marks = implode(', ', array_fill(0, count($row), '?'));
        self::query("INSERT INTO `$table` ($cols) VALUES ($marks)", array_values($row));
        return (int) self::pdo()->lastInsertId();
    }

    // Обновить строку по id: Db::update('flowers', 5, ['priceKop' => 1200])
    public static function update(string $table, int $id, array $row): int
    {
        $set = implode(', ', array_map(fn ($c) => "`$c` = ?", array_keys($row)));
        return self::exec("UPDATE `$table` SET $set WHERE id = ?", [...array_values($row), $id]);
    }

    // «IN (?, ?, ?)» для списка значений. Пустой список → «IN (NULL)», ничего не найдётся.
    public static function in(array $values): string
    {
        return $values ? 'IN (' . implode(', ', array_fill(0, count($values), '?')) . ')' : 'IN (NULL)';
    }

    // Всё или ничего: ошибка внутри — изменения откатываются.
    public static function tx(callable $fn): mixed
    {
        $pdo = self::pdo();
        $pdo->beginTransaction();
        try {
            $r = $fn();
            $pdo->commit();
            return $r;
        } catch (Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    // Нарушение уникального ключа (тот же slug, повтор заказа)
    public static function isDuplicate(Throwable $e): bool
    {
        return $e instanceof PDOException && ($e->errorInfo[1] ?? null) === 1062;
    }

    public static function json(mixed $v): string
    {
        return json_encode($v, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    public static function fromJson(?string $s, mixed $default = []): mixed
    {
        if ($s === null || $s === '') return $default;
        $v = json_decode($s, true);
        return $v ?? $default;
    }

    // Применить новые файлы из app/migrations (001_init.sql, 002_…). Каждый — один раз, по порядку.
    // Запускается сам при каждом запросе (index.php): если новых файлов нет, это одна короткая проверка.
    public static function migrate(): array
    {
        $pdo = self::pdo();
        $pdo->exec('CREATE TABLE IF NOT EXISTS migrations (name VARCHAR(190) PRIMARY KEY, appliedAt DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
        $files = glob(APP_DIR . '/migrations/*.sql');
        sort($files);
        $done = array_flip(self::pdo()->query('SELECT name FROM migrations')->fetchAll(PDO::FETCH_COLUMN));
        $applied = [];
        foreach ($files as $f) {
            $name = basename($f);
            if (isset($done[$name])) continue;
            // Чтобы два одновременных захода не применили одно и то же
            if (!self::value('SELECT GET_LOCK(?, 10)', ['artflora-migrate'])) throw new RuntimeException('База занята обновлением, попробуйте через минуту');
            try {
                if (self::value('SELECT COUNT(*) FROM migrations WHERE name = ?', [$name])) continue;
                foreach (self::splitSql(file_get_contents($f)) as $stmt) $pdo->exec($stmt);
                self::exec('INSERT INTO migrations (name) VALUES (?)', [$name]);
                $applied[] = $name;
            } finally {
                self::value('SELECT RELEASE_LOCK(?)', ['artflora-migrate']);
            }
        }
        return $applied;
    }

    // Делим файл на запросы по «;» в конце строки, комментарии «--» убираем.
    public static function splitSql(string $sql): array
    {
        $lines = array_filter(explode("\n", $sql), fn ($l) => !str_starts_with(ltrim($l), '--'));
        $sql = preg_replace('/\s--\s.*$/m', '', implode("\n", $lines));
        return array_values(array_filter(array_map('trim', preg_split('/;\s*$/m', $sql)), fn ($s) => $s !== ''));
    }
}

<?php
group('резервная копия (база)');
Db::migrate();
test('копия → проверка восстановления → строки сходятся', function () {
    $dir = sys_get_temp_dir() . '/artflora-backup-test-' . bin2hex(random_bytes(3));
    mkdir($dir);
    $file = "$dir/db-test.sql.gz";
    $counts = Backup::dump($file);
    ok(isset($counts['orders'], $counts['flowers']), 'все таблицы в копии');
    eq(Backup::expected($file), $counts);
    eq(Backup::check($file), count($counts));
    eq((int) Db::value("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name LIKE 'zz\\\\_check\\\\_%'"), 0, 'временные таблицы убраны');
    // Обрезанная копия не проходит
    $gz = gzencode(substr(gzdecode(file_get_contents($file)), 0, -20));
    file_put_contents("$dir/broken.sql.gz", $gz);
    $failed = false;
    try { Backup::check("$dir/broken.sql.gz"); } catch (RuntimeException) { $failed = true; }
    ok($failed, 'обрезанная копия должна не пройти');
    array_map('unlink', glob("$dir/*"));
    rmdir($dir);
});
test('текст с кавычками и переводом строки восстанавливается как был', function () {
    $dir = sys_get_temp_dir() . '/artflora-backup-test-' . bin2hex(random_bytes(3));
    mkdir($dir);
    $text = "строка 1\nстрока 2; 'кавычки' \"двойные\" \\ слэш);";
    Db::exec('DELETE FROM reminders WHERE phone = ?', ['+375000000000']);
    Db::insert('reminders', ['name' => $text, 'phone' => '+375000000000', 'occasion' => 'тест', 'date' => '2026-01-01']);
    Backup::dump("$dir/db.sql.gz");
    Db::exec('DELETE FROM reminders WHERE phone = ?', ['+375000000000']);
    Backup::restore("$dir/db.sql.gz");
    eq(Db::value('SELECT name FROM reminders WHERE phone = ?', ['+375000000000']), $text);
    Db::exec('DELETE FROM reminders WHERE phone = ?', ['+375000000000']);
    array_map('unlink', glob("$dir/*"));
    rmdir($dir);
});
test('старые копии удаляются через 14 дней', function () {
    $dir = sys_get_temp_dir() . '/artflora-backup-test-' . bin2hex(random_bytes(3));
    mkdir($dir);
    $now = strtotime('2026-10-26 04:00');
    foreach (['2026-10-01', '2026-10-11', '2026-10-12', '2026-10-26'] as $d) touch("$dir/db-$d.sql.gz");
    eq(Backup::prune($dir, $now), 2);
    eq(array_map('basename', glob("$dir/*")), ['db-2026-10-12.sql.gz', 'db-2026-10-26.sql.gz']);
    array_map('unlink', glob("$dir/*"));
    rmdir($dir);
});
test('заказы старше 3 лет удаляются, свежие остаются', function () {
    $mk = fn (int $n, string $at) => Db::exec("INSERT INTO orders (number, createdAt) SELECT ?, ? FROM DUAL", [$n, $at]);
    Db::exec('DELETE FROM orders WHERE number IN (990001, 990002)');
    $mode = Db::value('SELECT @@SESSION.sql_mode');
    Db::exec("SET SESSION sql_mode = ''"); // остальные поля заказа тесту не важны
    try {
        $mk(990001, date('Y-m-d H:i:s', strtotime('-3 years -2 days')));
        $mk(990002, date('Y-m-d H:i:s', strtotime('-3 years +2 days')));
    } catch (Throwable $e) {
        ok(false, 'не удалось создать тестовый заказ: ' . $e->getMessage());
    } finally {
        Db::exec('SET SESSION sql_mode = ?', [$mode]);
    }
    ok(Maintenance::purgeOrders() >= 1);
    eq((int) Db::value('SELECT COUNT(*) FROM orders WHERE number = 990001'), 0);
    eq((int) Db::value('SELECT COUNT(*) FROM orders WHERE number = 990002'), 1);
    Db::exec('DELETE FROM orders WHERE number IN (990001, 990002)');
});

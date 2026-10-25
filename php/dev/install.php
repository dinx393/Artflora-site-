<?php
// Установка и обновление PHP-версии сайта на хостинге одной командой (в панели → «Shell-клиент»):
//
//   curl -fsSL https://raw.githubusercontent.com/dinx393/Artflora-site-/refs/heads/claude/project-thread-b45kuf/php/dev/install.php -o ~/install.php && php ~/install.php test.artflora.by
//
// Что делает:
//  1) скачивает свежий код с GitHub и кладёт его в ~/www/<сайт> (app/config.php и фото в uploads/ не трогает);
//  2) чужие файлы, которые уже лежали в папке сайта, не удаляет, а переносит в ~/backup-<сайт>-<дата>;
//  3) при первой установке спрашивает базу данных и пароль админки и сам пишет app/config.php.
// Повторный запуск = обновление сайта.

const REPO_ZIP = 'https://codeload.github.com/dinx393/Artflora-site-/tar.gz/refs/heads/claude/project-thread-b45kuf';
const MARKER = 'app/routes/00-core.php'; // по этому файлу узнаём «наш» сайт

function say(string $s): void { echo "\n== $s\n"; }
function fail(string $s): never { fwrite(STDERR, "\nОшибка: $s\n"); exit(1); }
function ask(string $q, string $default = ''): string
{
    echo $q . ($default !== '' ? " [$default]" : '') . ': ';
    $a = trim((string) fgets(STDIN));
    return $a === '' ? $default : $a;
}

if (PHP_VERSION_ID < 80100) fail('нужен PHP 8.1 или новее, а здесь ' . PHP_VERSION . '. Выберите версию PHP в панели для сайта и для Shell (обычно команда php81 или /opt/php81/bin/php).');
foreach (['pdo_mysql', 'mbstring', 'phar', 'zlib', 'openssl'] as $ext) {
    if (!extension_loaded($ext)) fail("в PHP нет расширения $ext — включите его в панели → PHP");
}

$domain = $argv[1] ?? 'test.artflora.by';
$home = getenv('HOME') ?: dirname(__DIR__);
$site = rtrim($argv[2] ?? "$home/www/$domain", '/');
if (!is_dir($site)) fail("нет папки сайта $site. Создайте сайт $domain в панели → «Сайты» или укажите путь вторым словом.");

say("Скачиваю код сайта");
$zipFile = sys_get_temp_dir() . '/artflora-' . bin2hex(random_bytes(4)) . '.tar.gz';
$ctx = stream_context_create(['http' => ['timeout' => 60, 'follow_location' => 1, 'user_agent' => 'artflora-install']]);
$data = @file_get_contents(getenv('ARTFLORA_ZIP') ?: REPO_ZIP, false, $ctx); // ARTFLORA_ZIP — свой архив, для проверки
if ($data === false || strlen($data) < 1000) fail('не удалось скачать код с GitHub. Есть ли у хостинга выход в интернет?');
file_put_contents($zipFile, $data);

$tmp = sys_get_temp_dir() . '/artflora-' . bin2hex(random_bytes(4));
try {
    (new PharData($zipFile))->extractTo($tmp);
} catch (Throwable $e) {
    fail('архив с GitHub не распаковался: ' . $e->getMessage());
}
unlink($zipFile);
$src = glob("$tmp/*/php")[0] ?? fail('в архиве нет папки php');

// Чужие файлы (старый сайт, заглушка хостинга) переносим в запасную папку
if (!is_file("$site/" . MARKER)) {
    $old = array_diff(scandir($site), ['.', '..']);
    if ($old) {
        $backup = "$home/backup-$domain-" . date('Ymd-His');
        mkdir($backup, 0750, true);
        foreach ($old as $f) rename("$site/$f", "$backup/$f");
        say('Старые файлы из папки сайта перенесены в ' . $backup);
    }
}

say("Копирую файлы в $site");
$skip = ['dev', 'tests', 'README.md', '.gitignore'];
$copy = function (string $from, string $to) use (&$copy): void {
    if (!is_dir($to)) mkdir($to, 0755, true);
    foreach (array_diff(scandir($from), ['.', '..']) as $f) {
        is_dir("$from/$f") ? $copy("$from/$f", "$to/$f") : copy("$from/$f", "$to/$f");
    }
};
foreach (array_diff(scandir($src), ['.', '..']) as $f) {
    if (in_array($f, $skip, true)) continue;
    if ($f === 'uploads') { // фото, загруженные в админке, остаются
        if (!is_dir("$site/uploads")) mkdir("$site/uploads", 0755, true);
        copy("$src/uploads/.htaccess", "$site/uploads/.htaccess");
        continue;
    }
    is_dir("$src/$f") ? $copy("$src/$f", "$site/$f") : copy("$src/$f", "$site/$f");
}
// Удалить из кода сайта файлы, которых больше нет в новой версии (только в app/lib, routes, views, content и assets/)
foreach (['app/lib', 'app/routes', 'app/views', 'app/content', 'assets'] as $dir) {
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator("$site/$dir", FilesystemIterator::SKIP_DOTS));
    foreach ($it as $file) {
        $rel = substr($file->getPathname(), strlen($site) + 1);
        if (!file_exists("$src/$rel")) unlink($file->getPathname());
    }
}
$rm = function (string $p) use (&$rm): void {
    if (is_dir($p) && !is_link($p)) { foreach (array_diff(scandir($p), ['.', '..']) as $f) $rm("$p/$f"); rmdir($p); } else unlink($p);
};
$rm($tmp);

$config = "$site/app/config.php";
if (!is_file($config)) {
    say('Первая установка: настройки');
    echo "Базу данных создайте заранее в панели → «Базы данных» → «Создать базу данных» (тип MySQL).\n";
    $dbName = ask('Имя базы данных');
    $dbUser = ask('Пользователь базы', $dbName);
    $dbPass = ask('Пароль пользователя базы');
    $dbHost = ask('Сервер базы (просто нажмите Enter)', 'localhost');
    try {
        new PDO("mysql:host=$dbHost;dbname=$dbName;charset=utf8mb4", $dbUser, $dbPass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    } catch (PDOException $e) {
        fail('не получилось подключиться к базе: ' . $e->getMessage() . "\nПроверьте имя, пользователя и пароль и запустите команду ещё раз.");
    }
    $admin = ask('Пароль для входа в админку (от 12 символов; Enter — придумать самой программе)');
    if ($admin === '') $admin = substr(strtr(base64_encode(random_bytes(12)), '+/=', 'xyz'), 0, 16);
    if (mb_strlen($admin) < 12) fail('пароль админки короче 12 символов');
    $values = [
        'db_host' => $dbHost, 'db_name' => $dbName, 'db_user' => $dbUser, 'db_pass' => $dbPass,
        'site_url' => "https://$domain", 'admin_password' => $admin, 'session_secret' => bin2hex(random_bytes(32)),
        'smtp_host' => 'smtp.gmail.com', 'smtp_port' => 465, 'smtp_user' => '', 'smtp_pass' => '', 'smtp_from' => '',
        'telegram_bot_token' => '', 'telegram_chat_id' => '',
    ];
    file_put_contents($config, "<?php\n// Настройки сайта. В GitHub не попадает. Почта: smtp_user — ящик, smtp_pass — пароль приложения.\nreturn " . var_export($values, true) . ";\n");
    chmod($config, 0600);
    echo "\nПароль админки: $admin\nЗапишите его. Посмотреть снова: grep admin_password $config\n";
}

say('Проверяю базу');
require "$site/app/bootstrap.php";
try {
    $applied = Db::migrate();
    echo $applied ? 'Добавлены таблицы: ' . implode(', ', $applied) . "\n" : "База в порядке\n";
} catch (Throwable $e) {
    fail('база не обновилась: ' . $e->getMessage());
}

say('Проверяю фото букетов');
$fixed = Uploads::shrinkAll();
echo $fixed ? "Уменьшено фото с iPhone: $fixed\n" : "Фото в порядке\n";

say('Готово');
echo "Сайт:    https://$domain\nАдминка: https://$domain/admin\n";
echo "Если https ещё не включён: панель → «SSL-сертификаты» → «Выпустить Let's Encrypt» для $domain.\n";
echo "Повтор уведомлений: панель → «Планировщик CRON», раз в 5 минут: " . PHP_BINARY . " $site/app/cron.php\n";

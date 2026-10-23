<?php
// Проверка расчётов без браузера: php tests/run.php
// Тесты с базой (RateLimit) запускаются, только если база настроена (app/config.php или ARTFLORA_DB_NAME).
require __DIR__ . '/../app/bootstrap.php';

$GLOBALS['__fails'] = 0;
$GLOBALS['__count'] = 0;
$GLOBALS['__group'] = '';

function group(string $name): void { $GLOBALS['__group'] = $name; }

function test(string $name, callable $fn): void
{
    $GLOBALS['__count']++;
    try {
        $fn();
        echo "  ✓ {$GLOBALS['__group']}: $name\n";
    } catch (Throwable $e) {
        $GLOBALS['__fails']++;
        echo "  ✗ {$GLOBALS['__group']}: $name\n    " . $e->getMessage() . ' (' . basename($e->getFile()) . ':' . $e->getLine() . ")\n";
    }
}

function eq(mixed $actual, mixed $expected, string $what = ''): void
{
    if ($actual !== $expected) {
        throw new RuntimeException(($what ? "$what: " : '') . 'ожидали ' . var_export($expected, true) . ', получили ' . var_export($actual, true));
    }
}

function ok(mixed $cond, string $what = 'условие не выполнено'): void
{
    if (!$cond) throw new RuntimeException($what);
}

function contains(string $hay, string $needle): void
{
    if (!str_contains($hay, $needle)) throw new RuntimeException("нет текста «{$needle}»");
}

$db = (bool) (Config::get('db_name'));
Config::override(array_merge(Config::all(), ['session_secret' => str_repeat('x', 40)]));
foreach (glob(__DIR__ . '/*Test.php') as $f) {
    if (str_contains(basename($f), 'Db') && !$db) { echo "  – " . basename($f) . ": пропущен, база не настроена\n"; continue; }
    require $f;
}
echo "\n{$GLOBALS['__count']} проверок, ошибок: {$GLOBALS['__fails']}\n";
exit($GLOBALS['__fails'] ? 1 : 0);

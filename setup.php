<?php

/**
 * CityCare Medical Centre: one-step developer setup.
 *
 * Usage:
 *   php setup.php            Install everything; seed only if the database is empty
 *   php setup.php --fresh    Drop all tables, re-migrate and re-seed (wipes data)
 *   php setup.php --no-assets  Skip npm install / npm run build
 *
 * Safe to run repeatedly: each step skips work that is already done.
 */

if (PHP_SAPI !== 'cli') {
    exit("Run this from the command line: php setup.php\n");
}

chdir(__DIR__);

$args = array_slice($argv, 1);
$fresh = in_array('--fresh', $args, true);
$skipAssets = in_array('--no-assets', $args, true);

// ---------------------------------------------------------------- helpers

function step(string $msg): void
{
    echo "\n\033[1;34m==>\033[0m \033[1m{$msg}\033[0m\n";
}

function ok(string $msg): void
{
    echo "    \033[32m✔\033[0m {$msg}\n";
}

function warn(string $msg): void
{
    echo "    \033[33m!\033[0m {$msg}\n";
}

function fail(string $msg): never
{
    fwrite(STDERR, "\n\033[1;31m✘ {$msg}\033[0m\n");
    exit(1);
}

function run(string $cmd): void
{
    echo "    \033[90m$ {$cmd}\033[0m\n";
    passthru($cmd, $code);
    if ($code !== 0) {
        fail("Command failed (exit {$code}): {$cmd}");
    }
}

function commandExists(string $cmd): bool
{
    $check = PHP_OS_FAMILY === 'Windows' ? "where {$cmd} 2>NUL" : "command -v {$cmd} 2>/dev/null";
    return trim((string) shell_exec($check)) !== '';
}

$php = escapeshellarg(PHP_BINARY);
$artisan = "{$php} artisan";

// ---------------------------------------------------------------- 1. requirements

step('Checking requirements');

if (version_compare(PHP_VERSION, '8.2.0', '<')) {
    fail('PHP 8.2+ is required, found ' . PHP_VERSION . ' (' . PHP_BINARY . ')');
}
ok('PHP ' . PHP_VERSION);

$missing = array_filter(
    ['pdo_mysql', 'mbstring', 'fileinfo', 'gd', 'zip', 'openssl'],
    fn ($ext) => ! extension_loaded($ext)
);
if ($missing) {
    fail('Missing PHP extensions: ' . implode(', ', $missing) . '. Enable them in php.ini.');
}
ok('PHP extensions');

if (! commandExists('composer')) {
    fail('Composer is not installed or not on PATH: https://getcomposer.org/download/');
}
ok('Composer');

if (! $skipAssets && ! commandExists('npm')) {
    fail('Node.js/npm is not installed or not on PATH (Node 18+). Or re-run with --no-assets.');
}
if (! $skipAssets) {
    ok('npm');
}

// ---------------------------------------------------------------- 2. PHP dependencies

step('Installing PHP dependencies');
run('composer install --no-interaction --prefer-dist');

// ---------------------------------------------------------------- 3. .env + key

step('Preparing environment file');

if (! file_exists('.env')) {
    copy('.env.example', '.env') || fail('Could not copy .env.example to .env');
    ok('Created .env from .env.example');
} else {
    ok('.env already exists (left unchanged)');
}

require __DIR__ . '/vendor/autoload.php';

// Parse .env without exporting it, so child artisan processes read the file themselves.
$env = Dotenv\Dotenv::createArrayBacked(__DIR__)->load();

if (empty($env['APP_KEY'])) {
    run("{$artisan} key:generate --ansi");
} else {
    ok('APP_KEY already set');
}

// ---------------------------------------------------------------- 4. database

step('Creating the database if it does not exist');

$driver = $env['DB_CONNECTION'] ?? 'mysql';
$pdo = null;

if ($driver === 'sqlite') {
    $file = $env['DB_DATABASE'] ?? 'database/database.sqlite';
    if (! file_exists($file)) {
        touch($file);
        ok("Created SQLite file {$file}");
    } else {
        ok("SQLite file {$file} exists");
    }
    $pdo = new PDO('sqlite:' . $file);
} elseif (in_array($driver, ['mysql', 'mariadb'], true)) {
    $host = $env['DB_HOST'] ?? '127.0.0.1';
    $port = $env['DB_PORT'] ?? '3306';
    $db = $env['DB_DATABASE'] ?? 'citycare';
    $user = $env['DB_USERNAME'] ?? 'root';
    $pass = $env['DB_PASSWORD'] ?? '';

    try {
        $server = new PDO("mysql:host={$host};port={$port}", $user, $pass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_TIMEOUT => 5,
        ]);
    } catch (PDOException $e) {
        fail("Cannot connect to MySQL at {$host}:{$port} as '{$user}'.\n"
            . "  Is MySQL running (XAMPP Control Panel → Start MySQL)? Check DB_* in .env.\n"
            . '  Error: ' . $e->getMessage());
    }

    $quoted = '`' . str_replace('`', '``', $db) . '`';
    $server->exec("CREATE DATABASE IF NOT EXISTS {$quoted} CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    ok("Database '{$db}' is ready on {$host}:{$port}");

    $pdo = new PDO("mysql:host={$host};port={$port};dbname={$db}", $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    ]);
} else {
    warn("DB_CONNECTION={$driver}: create the database yourself; continuing with migrations.");
}

// ---------------------------------------------------------------- 5. migrations + seed

step($fresh ? 'Rebuilding all tables and seeding (--fresh)' : 'Running migrations');

if ($fresh) {
    echo "    Seeding generates ~6 months of demo activity; this can take a minute or two.\n";
    run("{$artisan} migrate:fresh --seed --force --ansi");
} else {
    run("{$artisan} migrate --force --ansi");

    $hasUsers = false;
    if ($pdo) {
        try {
            $hasUsers = (int) $pdo->query('SELECT COUNT(*) FROM users')->fetchColumn() > 0;
        } catch (PDOException) {
            $hasUsers = false;
        }
    }

    if ($hasUsers) {
        ok('Database already has data; skipping seeders (use --fresh to reset demo data)');
    } else {
        step('Seeding demo data');
        echo "    Seeding generates ~6 months of demo activity; this can take a minute or two.\n";
        run("{$artisan} db:seed --force --ansi");
    }
}

// ---------------------------------------------------------------- 6. storage link

step('Linking public storage (profile photos)');

if (file_exists('public/storage')) {
    ok('public/storage already linked');
} else {
    run("{$artisan} storage:link --ansi");
}

// ---------------------------------------------------------------- 7. front-end assets

if ($skipAssets) {
    step('Skipping front-end assets (--no-assets)');
} else {
    step('Installing and building front-end assets');
    run('npm install');
    run('npm run build');
}

// ---------------------------------------------------------------- 8. clear caches

step('Clearing cached config/routes/views');
run("{$artisan} optimize:clear --ansi");

// ---------------------------------------------------------------- done

echo "\n\033[1;32m✔ CityCare is ready.\033[0m\n\n";
echo "  Start the app:   composer serve-fast   (recommended on XAMPP: OPcache on)\n";
echo "              or:  php artisan serve\n";
echo "  Then open:       http://127.0.0.1:8000\n";
echo "  Demo logins:     see README section 4; every password is \"password\"\n";
echo "  Reset demo data: php setup.php --fresh\n\n";

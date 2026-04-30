<?php

use Symfony\Component\Dotenv\Dotenv;

require dirname(__DIR__).'/vendor/autoload.php';

if (method_exists(Dotenv::class, 'bootEnv')) {
    (new Dotenv())->bootEnv(dirname(__DIR__).'/.env');
}

if ($_SERVER['APP_DEBUG']) {
    umask(0000);
}

// Ensure a test SQLite database and schema exist when running tests
if ((getenv('APP_ENV') ?: $_SERVER['APP_ENV'] ?? null) === 'test') {
    // Use a persistent temp file so schema is available to clients in tests
    $dbFile = sys_get_temp_dir().'/mimine_phpunit_test.sqlite';
    if (file_exists($dbFile)) {
        @unlink($dbFile);
    }
    $dsn = 'sqlite:///' . $dbFile;
    putenv('DATABASE_URL='.$dsn);
    $_ENV['DATABASE_URL'] = $dsn;
    $_SERVER['DATABASE_URL'] = $dsn;

    // Leave schema creation to individual tests (they create schemas with SchemaTool)
    // create an empty sqlite file so tests can use it
    if (!file_exists($dbFile)) {
        @touch($dbFile);
    }
}

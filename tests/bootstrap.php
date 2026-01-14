<?php

use Symfony\Component\Dotenv\Dotenv;

require dirname(__DIR__).'/vendor/autoload.php';

if (method_exists(Dotenv::class, 'bootEnv')) {
    new Dotenv()->bootEnv(dirname(__DIR__).'/.env');
}

if ($_SERVER['APP_DEBUG']) {
    umask(0000);
}

// Delete test database to ensure fresh schema on each test run
$dataDir = dirname(__DIR__).'/var/data';
$testDatabase = 'test.db';

$dbPath = $dataDir.'/'.$testDatabase;
if (file_exists($dbPath)) {
    unlink($dbPath);
}
// Also remove WAL and SHM files if they exist
foreach (['-wal', '-shm'] as $suffix) {
    $walPath = $dbPath.$suffix;
    if (file_exists($walPath)) {
        unlink($walPath);
    }
}

// Recreate database with current schema
passthru(
    'php bin/console doctrine:schema:create --env=test --quiet 2>/dev/null',
);

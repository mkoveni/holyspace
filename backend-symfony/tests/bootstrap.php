<?php

declare(strict_types=1);

use Symfony\Component\Dotenv\Dotenv;

require dirname(__DIR__).'/vendor/autoload.php';

if (method_exists(Dotenv::class, 'bootEnv')) {
    (new Dotenv())->bootEnv(dirname(__DIR__).'/.env');
}

// Parallel runs (paratest) give every worker its own database and picture directory, so tests never see each other's rows or files.
// Set after the dotenv files are read: `.env.test` overwrites $_SERVER and $_ENV, and a worker must not fall back to the shared path.
$worker = (string) (getenv('TEST_TOKEN') ?: ($_SERVER['TEST_TOKEN'] ?? ''));
if ('' !== $worker && 1 === preg_match('/^\d{1,3}$/', $worker)) {
    $url = (string) (getenv('DATABASE_URL') ?: ($_SERVER['DATABASE_URL'] ?? ''));
    $overrides = ['MEDIA_STORAGE_PATH' => '/tmp/onechurch-media-test-'.$worker];
    if ('' !== $url) {
        $overrides['DATABASE_URL'] = preg_replace('~/([^/?]+)(\?|$)~', '/$1_'.$worker.'$2', $url, 1) ?? $url;
    }
    foreach ($overrides as $name => $value) {
        putenv($name.'='.$value);
        $_ENV[$name] = $_SERVER[$name] = $value;
    }
}

if ($_SERVER['APP_DEBUG']) {
    umask(0000);
}

// Make sure the test database exists before any test (including HTTP tests) touches it.
App\Tests\Support\DatabaseTestCase::createDatabaseIfMissing();

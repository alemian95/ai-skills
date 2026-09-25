<?php

declare(strict_types=1);

// Default values, overridden key by key by settings.local.php (not versioned,
// created only once on the server). The configuration files are PHP and not YAML/ENV:
// if a protection failed, the web server would execute them instead of showing their contents.

use App\Config\Settings;
use Monolog\Level;

$local = is_file(__DIR__ . '/settings.local.php') ? require __DIR__ . '/settings.local.php' : [];
if (!is_array($local)) {
    throw new UnexpectedValueException('config/settings.local.php must return an array');
}

// The types are checked by the constructor at runtime (strict_types): PHPStan cannot know them from an array.
// @phpstan-ignore argument.type
return new Settings(...[
    'appDir' => dirname(__DIR__),
    'publicDir' => dirname(__DIR__, 2),     // document root: change it if app/ is above the document root
    'varDir' => dirname(__DIR__) . '/var',  // writable data: SQLite database, logs, sessions, cache
    'debug' => false,
    'https' => true,                        // Secure cookie and HSTS; false only in development over http://
    'timezone' => 'Europe/Rome',
    'logLevel' => Level::Warning,
    'sessionName' => 'app_session',
    'database' => [
        'driver' => 'pdo_sqlite',
        'path' => dirname(__DIR__) . '/var/database.sqlite',
    ],
    ...$local,
]);

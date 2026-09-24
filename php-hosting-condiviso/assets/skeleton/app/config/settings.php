<?php

declare(strict_types=1);

// Valori predefiniti, sovrascritti chiave per chiave da settings.local.php (non versionato,
// creato una volta sola sul server). I file di configurazione sono PHP e non YAML/ENV:
// se una protezione fallisse, il web server li eseguirebbe invece di mostrarne il contenuto.

use App\Config\Settings;
use Monolog\Level;

$local = is_file(__DIR__ . '/settings.local.php') ? require __DIR__ . '/settings.local.php' : [];
if (!is_array($local)) {
    throw new UnexpectedValueException('config/settings.local.php deve restituire un array');
}

// I tipi li verifica il costruttore a runtime (strict_types): PHPStan non può conoscerli da un array.
// @phpstan-ignore argument.type
return new Settings(...[
    'appDir' => dirname(__DIR__),
    'publicDir' => dirname(__DIR__, 2),     // document root: cambiala se app/ sta sopra la document root
    'varDir' => dirname(__DIR__) . '/var',  // dati scrivibili: database SQLite, log, sessioni, cache
    'debug' => false,
    'https' => true,                        // cookie Secure e HSTS; false solo in sviluppo su http://
    'timezone' => 'Europe/Rome',
    'logLevel' => Level::Warning,
    'sessionName' => 'app_session',
    'database' => [
        'driver' => 'pdo_sqlite',
        'path' => dirname(__DIR__) . '/var/database.sqlite',
    ],
    ...$local,
]);

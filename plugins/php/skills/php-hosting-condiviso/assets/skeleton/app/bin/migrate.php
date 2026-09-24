<?php

declare(strict_types=1);

// Applica le migrazioni in sospeso. Pensato per il cron del pannello (nessun accesso SSH):
//   /percorso/php84 /home/utente/public_html/app/bin/migrate.php
// Opzioni: --status (elenca senza applicare).
// Attenzione: il "php" del cron spesso NON è la stessa versione del sito. Usa il percorso completo
// dell'eseguibile 8.4 indicato dal pannello; con una versione vecchia Composer si ferma con un messaggio chiaro.

use App\Database\Migrator;
use Psr\Container\ContainerInterface;

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit(1);
}

try {
    /** @var ContainerInterface $container */
    $container = require dirname(__DIR__) . '/bootstrap.php';
    $migrator = $container->get(Migrator::class);
    assert($migrator instanceof Migrator);

    if (in_array('--status', $argv, true)) {
        foreach ($migrator->status() as $version => $applied) {
            echo ($applied ? '[x] ' : '[ ] ') . $version . PHP_EOL;
        }
        exit(0);
    }

    $applied = $migrator->migrate();
    // Nessun output se non c'è nulla da fare: il cron non invia email a vuoto.
    foreach ($applied as $version) {
        echo 'Applicata: ' . $version . PHP_EOL;
    }
    exit(0);
} catch (Throwable $e) {
    fwrite(STDERR, $e::class . ': ' . $e->getMessage() . PHP_EOL);
    exit(1);
}

<?php

declare(strict_types=1);

// Front controller: l'unico file PHP raggiungibile dal web. Tutto il resto sta in app/.
//
// Questo file usa volutamente solo sintassi compatibile con qualsiasi versione di PHP:
// se il server ha una versione troppo vecchia, l'autoloader di Composer lo segnala
// (vendor/composer/platform_check.php) invece di produrre un errore di sintassi muto.
//
// Se l'hosting permette di caricare file SOPRA la document root, sposta app/ lì
// e cambia questa riga in: $appDir = dirname(__DIR__) . '/app';
$appDir = __DIR__ . '/app';

require $appDir . '/vendor/autoload.php';
require $appDir . '/web.php';

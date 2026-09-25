<?php

declare(strict_types=1);

// Front controller: the only PHP file reachable from the web. Everything else lives in app/.
//
// This file deliberately uses only syntax compatible with any PHP version:
// if the server has a version that is too old, Composer's autoloader reports it
// (vendor/composer/platform_check.php) instead of producing a silent syntax error.
//
// If the hosting allows uploading files ABOVE the document root, move app/ there
// and change this line to: $appDir = dirname(__DIR__) . '/app';
$appDir = __DIR__ . '/app';

require $appDir . '/vendor/autoload.php';
require $appDir . '/web.php';

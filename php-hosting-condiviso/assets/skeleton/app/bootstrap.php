<?php

declare(strict_types=1);

// Punto di avvio comune a web (web.php) e CLI (bin/*): ambiente PHP, errori, container.

use App\Config\Settings;
use DI\ContainerBuilder;

require_once __DIR__ . '/vendor/autoload.php';

$settings = Settings::load(__DIR__);

// Impostazioni applicate a runtime: funzionano anche dove .user.ini o php.ini non sono modificabili.
error_reporting(E_ALL);
ini_set('display_errors', $settings->debug ? '1' : '0');
ini_set('log_errors', '1');
ini_set('error_log', $settings->varDir . '/log/php-errors.log');
date_default_timezone_set($settings->timezone);

set_error_handler(static function (int $severity, string $message, string $file, int $line): bool {
    if ((error_reporting() & $severity) === 0) {
        return false;
    }

    throw new ErrorException($message, 0, $severity, $file, $line);
});

$builder = new ContainerBuilder();
$builder->useAutowiring(true);
$builder->useAttributes(false);
$builder->addDefinitions(__DIR__ . '/config/container.php', [Settings::class => $settings]);

return $builder->build();

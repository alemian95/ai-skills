<?php

declare(strict_types=1);

use Laminas\Diactoros\ServerRequestFactory;
use Laminas\HttpHandlerRunner\Emitter\SapiEmitter;
use Psr\Container\ContainerInterface;
use Psr\Http\Server\RequestHandlerInterface;

require dirname(__DIR__) . '/vendor/autoload.php';

// Ogni punto di ingresso (web, CLI) converte warning/notice/deprecation in eccezioni.
error_reporting(E_ALL);
set_error_handler(static function (int $severity, string $message, string $file, int $line): bool {
    if ((error_reporting() & $severity) === 0) {
        return false; // livello escluso o soppresso: lascia gestire a PHP
    }

    throw new ErrorException($message, 0, $severity, $file, $line);
});

/** @var ContainerInterface $container */
$container = require dirname(__DIR__) . '/config/bootstrap.php';

$app = $container->get(RequestHandlerInterface::class);
assert($app instanceof RequestHandlerInterface);

new SapiEmitter()->emit($app->handle(ServerRequestFactory::fromGlobals()));

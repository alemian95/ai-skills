<?php

declare(strict_types=1);

use Laminas\Diactoros\ServerRequestFactory;
use Laminas\HttpHandlerRunner\Emitter\SapiEmitter;
use Psr\Container\ContainerInterface;
use Psr\Http\Server\RequestHandlerInterface;

/** @var ContainerInterface $container */
$container = require __DIR__ . '/bootstrap.php';

$app = $container->get(RequestHandlerInterface::class);
assert($app instanceof RequestHandlerInterface);

new SapiEmitter()->emit($app->handle(ServerRequestFactory::fromGlobals()));

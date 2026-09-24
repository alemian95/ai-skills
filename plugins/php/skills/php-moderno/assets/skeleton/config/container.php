<?php

declare(strict_types=1);

use App\Config\Settings;
use App\Http\Middleware\ErrorHandlerMiddleware;
use FastRoute\Dispatcher;
use FastRoute\RouteCollector;
use Laminas\Diactoros\ResponseFactory;
use Laminas\Diactoros\StreamFactory;
use Middlewares\FastRoute;
use Middlewares\RequestHandler;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Log\LoggerInterface;
use Relay\Relay;

use function DI\get;
use function FastRoute\simpleDispatcher;

return [
    Settings::class => static fn(): Settings => Settings::fromEnvironment(),

    // Interfacce PSR-17 → implementazione Diactoros: cambiare implementazione tocca solo queste righe.
    ResponseFactoryInterface::class => get(ResponseFactory::class),
    StreamFactoryInterface::class => get(StreamFactory::class),

    LoggerInterface::class => static function (Settings $settings): LoggerInterface {
        $logger = new Logger('app');
        $logger->pushHandler(new StreamHandler($settings->logStream, $settings->logLevel));

        return $logger;
    },

    Dispatcher::class => static function (): Dispatcher {
        /** @var callable(RouteCollector): void $routes */
        $routes = require __DIR__ . '/routes.php';

        return simpleDispatcher($routes);
    },

    // Pipeline dell'applicazione, dall'esterno verso l'interno:
    // la gestione degli errori avvolge tutto, RequestHandler chiude la catena.
    // I parametri tipizzati della factory vengono risolti da PHP-DI.
    RequestHandlerInterface::class => static fn(
        ContainerInterface $container,
        ErrorHandlerMiddleware $errors,
        Dispatcher $routes,
        ResponseFactoryInterface $responses,
    ): RequestHandlerInterface => new Relay([
        $errors,
        new FastRoute($routes, $responses),
        new RequestHandler($container),
    ]),
];

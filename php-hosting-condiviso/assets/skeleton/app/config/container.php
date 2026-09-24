<?php

declare(strict_types=1);

use App\Config\Settings;
use App\Database\ConnectionFactory;
use App\Database\Migrator;
use App\Http\Middleware\BasePathMiddleware;
use App\Http\Middleware\CsrfMiddleware;
use App\Http\Middleware\ErrorMiddleware;
use App\Http\Middleware\SecurityHeadersMiddleware;
use App\Http\SessionPersistenceFactory;
use App\View\TwigFactory;
use Doctrine\DBAL\Connection;
use FastRoute\Dispatcher;
use FastRoute\RouteCollector;
use Laminas\Diactoros\ResponseFactory;
use Laminas\Diactoros\StreamFactory;
use Mezzio\Session\SessionMiddleware;
use Mezzio\Session\SessionPersistenceInterface;
use Middlewares\FastRoute;
use Middlewares\RequestHandler;
use Monolog\Handler\RotatingFileHandler;
use Monolog\Logger;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Log\LoggerInterface;
use Relay\Relay;
use Twig\Environment;

use function DI\get;
use function FastRoute\simpleDispatcher;

return [
    ResponseFactoryInterface::class => get(ResponseFactory::class),
    StreamFactoryInterface::class => get(StreamFactory::class),

    LoggerInterface::class => static fn(Settings $settings): LoggerInterface => new Logger('app', [
        new RotatingFileHandler($settings->varDir . '/log/app.log', 14, $settings->logLevel),
    ]),

    Connection::class => static fn(Settings $settings): Connection => ConnectionFactory::create($settings->database),
    Environment::class => static fn(Settings $settings): Environment => TwigFactory::create($settings),
    SessionPersistenceInterface::class => static fn(Settings $settings): SessionPersistenceInterface
        => SessionPersistenceFactory::create($settings),

    Migrator::class => static fn(Connection $db, Settings $settings): Migrator => new Migrator(
        $db,
        $settings->appDir . '/migrations',
        $settings->varDir . '/migrate.lock',
    ),

    Dispatcher::class => static function (): Dispatcher {
        /** @var callable(RouteCollector): void $routes */
        $routes = require __DIR__ . '/routes.php';

        return simpleDispatcher($routes);
    },

    // Pipeline, dall'esterno verso l'interno. BasePath per primo: serve anche alle pagine d'errore.
    // Ordine dei parametri = ordine dei middleware.
    RequestHandlerInterface::class => static fn(
        ContainerInterface $container,
        BasePathMiddleware $basePath,
        SecurityHeadersMiddleware $securityHeaders,
        ErrorMiddleware $errors,
        SessionMiddleware $session,
        Dispatcher $routes,
        ResponseFactoryInterface $responses,
        CsrfMiddleware $csrf,
    ): RequestHandlerInterface => new Relay([
        $basePath,
        $securityHeaders,
        $errors,
        $session,
        new FastRoute($routes, $responses),  // prima il routing: 404/405 corretti anche per POST/DELETE
        $csrf,
        new RequestHandler($container),
    ]),
];

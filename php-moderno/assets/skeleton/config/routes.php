<?php

declare(strict_types=1);

use App\Http\Handler\HealthHandler;
use App\Http\Handler\HelloHandler;
use FastRoute\RouteCollector;

return static function (RouteCollector $r): void {
    $r->get('/health', HealthHandler::class);
    $r->get('/hello/{name}', HelloHandler::class);
};

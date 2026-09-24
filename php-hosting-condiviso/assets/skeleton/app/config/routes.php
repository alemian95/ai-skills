<?php

declare(strict_types=1);

use App\Contact\ContactHandler;
use App\Http\Handler\HomeHandler;
use FastRoute\RouteCollector;

return static function (RouteCollector $r): void {
    $r->get('/', HomeHandler::class);
    $r->addRoute(['GET', 'POST'], '/contatti', ContactHandler::class);
};

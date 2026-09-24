<?php

declare(strict_types=1);

namespace App\Http\Handler;

use App\View\View;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

final readonly class HomeHandler implements RequestHandlerInterface
{
    public function __construct(private View $view) {}

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        return $this->view->render($request, 'home.html.twig');
    }
}

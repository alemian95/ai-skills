<?php

declare(strict_types=1);

namespace App\Http\Handler;

use App\Http\JsonResponder;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

final readonly class HealthHandler implements RequestHandlerInterface
{
    public function __construct(private JsonResponder $json) {}

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        return $this->json->respond(['status' => 'ok']);
    }
}

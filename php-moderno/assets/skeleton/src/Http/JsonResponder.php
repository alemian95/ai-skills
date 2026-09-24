<?php

declare(strict_types=1);

namespace App\Http;

use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamFactoryInterface;

/**
 * Crea risposte JSON tramite le factory PSR-17: ogni chiamata produce una risposta nuova,
 * nessuna istanza di Response condivisa tra richieste.
 */
final readonly class JsonResponder
{
    public function __construct(
        private ResponseFactoryInterface $responses,
        private StreamFactoryInterface $streams,
    ) {}

    /**
     * @param array<string, mixed> $data
     */
    public function respond(array $data, int $status = 200): ResponseInterface
    {
        $json = json_encode($data, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        return $this->responses->createResponse($status)
            ->withHeader('Content-Type', 'application/json; charset=utf-8')
            ->withBody($this->streams->createStream($json));
    }
}

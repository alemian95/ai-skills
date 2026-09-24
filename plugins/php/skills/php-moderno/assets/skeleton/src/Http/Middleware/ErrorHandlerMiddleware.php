<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Config\Settings;
use App\Http\JsonResponder;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * Ultimo confine: qualsiasi Throwable non gestito diventa un 500, viene registrato nel log
 * e i dettagli escono verso il client solo in modalità debug.
 */
final readonly class ErrorHandlerMiddleware implements MiddlewareInterface
{
    public function __construct(
        private JsonResponder $json,
        private LoggerInterface $logger,
        private Settings $settings,
    ) {}

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        try {
            return $handler->handle($request);
        } catch (Throwable $e) {
            $this->logger->error('Eccezione non gestita: {message}', [
                'message' => $e->getMessage(),
                'exception' => $e,
                'method' => $request->getMethod(),
                'path' => $request->getUri()->getPath(),
            ]);

            $body = ['error' => 'Errore interno del server'];
            if ($this->settings->debug) {
                $body['exception'] = [
                    'class' => $e::class,
                    'message' => $e->getMessage(),
                    'file' => $e->getFile() . ':' . $e->getLine(),
                    'trace' => explode("\n", $e->getTraceAsString()),
                ];
            }

            return $this->json->respond($body, 500);
        }
    }
}

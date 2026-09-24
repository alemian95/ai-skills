<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Config\Settings;
use App\View\View;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * Eccezioni non gestite → 500; risposte d'errore senza corpo (404, 405, 403 CSRF…) → pagina HTML.
 * I dettagli tecnici compaiono solo in debug; in produzione vanno solo nel log.
 */
final readonly class ErrorMiddleware implements MiddlewareInterface
{
    public function __construct(
        private View $view,
        private LoggerInterface $logger,
        private Settings $settings,
    ) {}

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        try {
            $response = $handler->handle($request);
        } catch (Throwable $e) {
            $this->logger->error('Eccezione non gestita: {message}', [
                'message' => $e->getMessage(),
                'exception' => $e,
                'method' => $request->getMethod(),
                'path' => $request->getUri()->getPath(),
            ]);

            return $this->page($request, 500, $e);
        }

        if ($response->getStatusCode() >= 400 && $response->getBody()->getSize() === 0) {
            $page = $this->page($request, $response->getStatusCode(), null);
            foreach ($response->getHeaders() as $name => $values) {
                $page = $page->withHeader($name, $values);
            }

            return $page->withHeader('Content-Type', 'text/html; charset=utf-8');
        }

        return $response;
    }

    private function page(ServerRequestInterface $request, int $status, ?Throwable $e): ResponseInterface
    {
        $details = $this->settings->debug && $e !== null
            ? ['class' => $e::class, 'message' => $e->getMessage(), 'where' => $e->getFile() . ':' . $e->getLine(), 'trace' => $e->getTraceAsString()]
            : null;

        try {
            return $this->view->render($request, 'errors/error.html.twig', ['status' => $status, 'details' => $details], $status);
        } catch (Throwable $renderError) {
            $this->logger->critical('Impossibile mostrare la pagina d\'errore', ['exception' => $renderError]);

            return $this->view->plain("Errore {$status}", $status);
        }
    }
}

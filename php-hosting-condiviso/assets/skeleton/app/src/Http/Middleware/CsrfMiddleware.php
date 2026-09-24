<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use LogicException;
use Mezzio\Session\SessionInterface;
use Mezzio\Session\SessionMiddleware;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Token sincronizzato per sessione: uno per sessione (funziona con più schede aperte),
 * confrontato con hash_equals, verificato automaticamente su ogni metodo che modifica stato.
 */
final readonly class CsrfMiddleware implements MiddlewareInterface
{
    public const string FIELD = '_csrf';
    public const string HEADER = 'X-CSRF-Token';
    private const string SESSION_KEY = '_csrf_token';
    private const array UNSAFE_METHODS = ['POST', 'PUT', 'PATCH', 'DELETE'];

    public function __construct(private ResponseFactoryInterface $responses) {}

    public static function token(SessionInterface $session): string
    {
        $token = $session->get(self::SESSION_KEY);
        if (!is_string($token) || $token === '') {
            $token = bin2hex(random_bytes(32));
            $session->set(self::SESSION_KEY, $token);
        }

        return $token;
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        if (!in_array($request->getMethod(), self::UNSAFE_METHODS, true)) {
            return $handler->handle($request);
        }

        $session = $request->getAttribute(SessionMiddleware::SESSION_ATTRIBUTE);
        if (!$session instanceof SessionInterface) {
            throw new LogicException('CsrfMiddleware deve seguire SessionMiddleware nella pipeline');
        }

        $body = $request->getParsedBody();
        $sent = is_array($body) && isset($body[self::FIELD]) ? $body[self::FIELD] : $request->getHeaderLine(self::HEADER);
        $expected = $session->get(self::SESSION_KEY);

        if (!is_string($sent) || !is_string($expected) || $expected === '' || !hash_equals($expected, $sent)) {
            return $this->responses->createResponse(403);
        }

        return $handler->handle($request);
    }
}

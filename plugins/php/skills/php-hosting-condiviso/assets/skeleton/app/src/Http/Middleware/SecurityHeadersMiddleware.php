<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Config\Settings;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Intestazioni di sicurezza su ogni risposta generata da PHP. Un handler può sovrascriverle
 * impostandole esplicitamente (es. una CSP diversa per una pagina specifica).
 */
final readonly class SecurityHeadersMiddleware implements MiddlewareInterface
{
    public function __construct(private Settings $settings) {}

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $response = $handler->handle($request);

        $headers = [
            'X-Content-Type-Options' => 'nosniff',
            'Referrer-Policy' => 'strict-origin-when-cross-origin',
            'X-Frame-Options' => 'DENY',
            'Content-Security-Policy' => "default-src 'self'; frame-ancestors 'none'; base-uri 'self'; form-action 'self'",
        ];
        if ($this->settings->https) {
            $headers['Strict-Transport-Security'] = 'max-age=31536000';
        }

        foreach ($headers as $name => $value) {
            if (!$response->hasHeader($name)) {
                $response = $response->withHeader($name, $value);
            }
        }

        return $response;
    }
}

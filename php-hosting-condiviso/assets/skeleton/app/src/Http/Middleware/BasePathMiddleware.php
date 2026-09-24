<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Rende l'applicazione indipendente dalla cartella di installazione (es. example.com/sito/):
 * toglie il prefisso dal percorso prima del routing e lo espone per generare link e URL degli asset.
 */
final readonly class BasePathMiddleware implements MiddlewareInterface
{
    public const string ATTRIBUTE = 'base_path';

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $script = $request->getServerParams()['SCRIPT_NAME'] ?? '';
        $script = is_string($script) ? str_replace('\\', '/', $script) : '';
        $base = rtrim(dirname($script), '/.');

        $path = $request->getUri()->getPath();
        foreach ([$script, $base] as $prefix) {
            if ($prefix !== '' && ($path === $prefix || str_starts_with($path, $prefix . '/'))) {
                $path = substr($path, strlen($prefix));
                break;
            }
        }

        return $handler->handle(
            $request
                ->withUri($request->getUri()->withPath($path === '' ? '/' : $path))
                ->withAttribute(self::ATTRIBUTE, $base),
        );
    }
}

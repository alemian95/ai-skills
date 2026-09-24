<?php

declare(strict_types=1);

namespace App\View;

use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Twig\Environment;

/**
 * Rende un template Twig in una risposta PSR-7. La richiesta viene passata al template
 * (come _request) così le funzioni di AppExtension leggono base path, sessione e flash
 * senza stato condiviso nei servizi.
 */
final readonly class View
{
    public function __construct(
        private Environment $twig,
        private ResponseFactoryInterface $responses,
        private StreamFactoryInterface $streams,
    ) {}

    /**
     * @param array<string, mixed> $context
     */
    public function render(ServerRequestInterface $request, string $template, array $context = [], int $status = 200): ResponseInterface
    {
        $html = $this->twig->render($template, ['_request' => $request, ...$context]);

        return $this->responses->createResponse($status)
            ->withHeader('Content-Type', 'text/html; charset=utf-8')
            ->withBody($this->streams->createStream($html));
    }

    public function plain(string $text, int $status): ResponseInterface
    {
        return $this->responses->createResponse($status)
            ->withHeader('Content-Type', 'text/plain; charset=utf-8')
            ->withBody($this->streams->createStream($text));
    }

    public function redirect(ServerRequestInterface $request, string $path, int $status = 303): ResponseInterface
    {
        return $this->responses->createResponse($status)
            ->withHeader('Location', AppExtension::basePath($request) . $path);
    }
}

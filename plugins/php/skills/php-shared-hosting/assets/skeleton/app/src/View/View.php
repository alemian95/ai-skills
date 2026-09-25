<?php

declare(strict_types=1);

namespace App\View;

use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Twig\Environment;

/**
 * Renders a Twig template into a PSR-7 response. The request is passed to the template
 * (as _request) so the AppExtension functions read base path, session and flash
 * without shared state in the services.
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

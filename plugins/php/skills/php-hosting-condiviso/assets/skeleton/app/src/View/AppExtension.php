<?php

declare(strict_types=1);

namespace App\View;

use App\Http\Flash;
use App\Http\Middleware\BasePathMiddleware;
use App\Http\Middleware\CsrfMiddleware;
use LogicException;
use Mezzio\Session\SessionInterface;
use Mezzio\Session\SessionMiddleware;
use Psr\Http\Message\ServerRequestInterface;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

final class AppExtension extends AbstractExtension
{
    public function __construct(private readonly string $publicDir) {}

    public static function basePath(ServerRequestInterface $request): string
    {
        $base = $request->getAttribute(BasePathMiddleware::ATTRIBUTE, '');

        return is_string($base) ? $base : '';
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('path', $this->path(...), ['needs_context' => true]),
            new TwigFunction('asset', $this->asset(...), ['needs_context' => true]),
            new TwigFunction('csrf_field', $this->csrfField(...), ['needs_context' => true, 'is_safe' => ['html']]),
            new TwigFunction('flashes', $this->flashes(...), ['needs_context' => true]),
        ];
    }

    /**
     * @param array<string, mixed> $context
     */
    public function path(array $context, string $path): string
    {
        return self::basePath($this->request($context)) . '/' . ltrim($path, '/');
    }

    /**
     * URL di un file in assets/ con versione basata sulla data di modifica: il browser
     * può tenerlo in cache a lungo e riceve la nuova versione dopo ogni caricamento via FTP.
     *
     * @param array<string, mixed> $context
     */
    public function asset(array $context, string $path): string
    {
        $path = 'assets/' . ltrim($path, '/');
        $mtime = @filemtime($this->publicDir . '/' . $path); // file mancante: URL senza versione, nessun errore

        return $this->path($context, $path) . ($mtime !== false ? '?v=' . $mtime : '');
    }

    /**
     * @param array<string, mixed> $context
     */
    public function csrfField(array $context): string
    {
        $session = $this->request($context)->getAttribute(SessionMiddleware::SESSION_ATTRIBUTE);
        if (!$session instanceof SessionInterface) {
            throw new LogicException('csrf_field() richiede una sessione attiva nella richiesta');
        }

        return sprintf(
            '<input type="hidden" name="%s" value="%s">',
            CsrfMiddleware::FIELD,
            htmlspecialchars(CsrfMiddleware::token($session), ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5, 'UTF-8'),
        );
    }

    /**
     * Messaggi flash da mostrare (e rimuovere dalla sessione). Senza sessione, ad esempio
     * nelle pagine d'errore generate prima di SessionMiddleware, restituisce un elenco vuoto.
     *
     * @param array<string, mixed> $context
     * @return list<string>
     */
    public function flashes(array $context): array
    {
        $session = $this->request($context)->getAttribute(SessionMiddleware::SESSION_ATTRIBUTE);

        return $session instanceof SessionInterface ? Flash::pull($session) : [];
    }

    /**
     * @param array<string, mixed> $context
     */
    private function request(array $context): ServerRequestInterface
    {
        $request = $context['_request'] ?? null;
        if (!$request instanceof ServerRequestInterface) {
            throw new LogicException('Template reso senza richiesta: usa View::render()');
        }

        return $request;
    }
}

<?php

declare(strict_types=1);

namespace App\Tests\Support;

use Mezzio\Session\Session;
use Mezzio\Session\SessionIdentifierAwareInterface;
use Mezzio\Session\SessionInterface;
use Mezzio\Session\SessionPersistenceInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Sessioni in memoria per i test: stesso contratto di PhpSessionPersistence, senza session_start().
 */
final class InMemorySessionPersistence implements SessionPersistenceInterface
{
    public const string COOKIE = 'test_session';

    /** @var array<string, array<string, mixed>> */
    private array $store = [];

    public function initializeSessionFromRequest(ServerRequestInterface $request): SessionInterface
    {
        $id = $request->getCookieParams()[self::COOKIE] ?? '';
        $id = is_string($id) && isset($this->store[$id]) ? $id : '';

        return new Session($id === '' ? [] : $this->store[$id], $id);
    }

    public function persistSession(SessionInterface $session, ResponseInterface $response): ResponseInterface
    {
        if (!$session->hasChanged()) {
            return $response;
        }

        $id = $session instanceof SessionIdentifierAwareInterface ? $session->getId() : '';
        if ($id === '' || $session->isRegenerated()) {
            $id = bin2hex(random_bytes(8));
        }
        /** @var array<string, mixed> $data chiavi di sessione sempre stringhe */
        $data = $session->toArray();
        $this->store[$id] = $data;

        return $response->withAddedHeader('Set-Cookie', self::COOKIE . '=' . $id . '; Path=/; HttpOnly');
    }
}

<?php

declare(strict_types=1);

namespace App\Http;

use Mezzio\Session\SessionInterface;

/**
 * Messages shown only once, usually after a redirect (Post/Redirect/Get).
 */
final class Flash
{
    private const string KEY = '_flash';

    public static function add(SessionInterface $session, string $message): void
    {
        $session->set(self::KEY, [...self::read($session), $message]);
    }

    /**
     * Returns the messages and removes them from the session.
     *
     * @return list<string>
     */
    public static function pull(SessionInterface $session): array
    {
        $messages = self::read($session);
        if ($messages !== []) {
            $session->unset(self::KEY);
        }

        return $messages;
    }

    /**
     * @return list<string>
     */
    private static function read(SessionInterface $session): array
    {
        $messages = $session->get(self::KEY, []);

        return is_array($messages) ? array_values(array_filter($messages, is_string(...))) : [];
    }
}

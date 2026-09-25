<?php

declare(strict_types=1);

namespace App\Http;

use App\Config\Settings;
use Mezzio\Session\Ext\PhpSessionPersistence;
use Mezzio\Session\SessionPersistenceInterface;
use RuntimeException;

/**
 * Native PHP sessions, but saved in app/var/sessions: on shared hosting the default folder
 * is often shared by several customers, and the system cleanup does not cover custom folders.
 */
final class SessionPersistenceFactory
{
    public static function create(Settings $settings): SessionPersistenceInterface
    {
        foreach ([
            'session.save_path' => $settings->varDir . '/sessions',
            'session.name' => $settings->sessionName,
            'session.use_strict_mode' => '1',
            'session.cookie_httponly' => '1',
            'session.cookie_secure' => $settings->https ? '1' : '0',
            'session.cookie_samesite' => 'Lax',
            'session.gc_maxlifetime' => '7200',
            'session.gc_probability' => '1',       // some distributions set it to zero and clean up via system cron
            'session.gc_divisor' => '100',
        ] as $key => $value) {
            if (ini_set($key, $value) === false) {
                throw new RuntimeException("Unable to set {$key}: the hosting prevents it");
            }
        }

        return new PhpSessionPersistence();
    }
}

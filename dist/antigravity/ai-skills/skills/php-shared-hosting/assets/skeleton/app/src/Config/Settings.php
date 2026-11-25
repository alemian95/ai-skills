<?php

declare(strict_types=1);

namespace App\Config;

use Monolog\Level;
use UnexpectedValueException;

/**
 * Immutable configuration. It is built in config/settings.php with named arguments:
 * the constructor's types validate the values and an unknown key in settings.local.php
 * immediately produces an error instead of being ignored.
 */
final readonly class Settings
{
    /**
     * @param array<string, mixed> $database parameters validated by ConnectionFactory
     */
    public function __construct(
        public string $appDir,
        public string $publicDir,
        public string $varDir,
        public bool $debug,
        public bool $https,
        public string $timezone,
        public Level $logLevel,
        public string $sessionName,
        public array $database,
    ) {}

    public static function load(string $appDir): self
    {
        $settings = require $appDir . '/config/settings.php';

        return $settings instanceof self
            ? $settings
            : throw new UnexpectedValueException('config/settings.php must return a Settings object');
    }
}

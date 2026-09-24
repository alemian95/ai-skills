<?php

declare(strict_types=1);

namespace App\Config;

use Monolog\Level;
use UnexpectedValueException;

/**
 * Configurazione immutabile. Si costruisce in config/settings.php con argomenti nominati:
 * i tipi del costruttore validano i valori e una chiave sconosciuta in settings.local.php
 * produce subito un errore invece di essere ignorata.
 */
final readonly class Settings
{
    /**
     * @param array<string, mixed> $database parametri validati da ConnectionFactory
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
            : throw new UnexpectedValueException('config/settings.php deve restituire un oggetto Settings');
    }
}

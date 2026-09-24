<?php

declare(strict_types=1);

namespace App\Config;

use Monolog\Level;
use UnexpectedValueException;

/**
 * Configurazione letta una sola volta dall'ambiente e poi trattata come valore immutabile.
 * Le classi ricevono Settings (o i singoli valori) per iniezione, mai getenv() sparsi nel codice.
 */
final readonly class Settings
{
    public function __construct(
        public bool $debug,
        public string $logStream,
        public Level $logLevel,
    ) {}

    public static function fromEnvironment(): self
    {
        $debug = filter_var(self::env('APP_DEBUG', 'false'), FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE)
            ?? throw new UnexpectedValueException('APP_DEBUG deve essere un valore booleano');

        $level = self::env('APP_LOG_LEVEL', $debug ? 'debug' : 'warning');

        return new self(
            debug: $debug,
            logStream: self::env('APP_LOG_STREAM', 'php://stderr'),
            logLevel: array_find(Level::cases(), static fn(Level $case): bool => strcasecmp($case->name, $level) === 0)
                ?? throw new UnexpectedValueException("APP_LOG_LEVEL non valido: {$level}"),
        );
    }

    private static function env(string $name, string $default): string
    {
        $value = getenv($name);

        return $value === false || $value === '' ? $default : $value;
    }
}

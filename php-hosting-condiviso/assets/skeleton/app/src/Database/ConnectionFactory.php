<?php

declare(strict_types=1);

namespace App\Database;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use UnexpectedValueException;

/**
 * Traduce la configurazione in parametri DBAL tipizzati. La connessione è pigra:
 * il database viene contattato solo alla prima query.
 */
final class ConnectionFactory
{
    /**
     * @param array<string, mixed> $config
     */
    public static function create(array $config): Connection
    {
        return match ($config['driver'] ?? null) {
            'pdo_sqlite' => DriverManager::getConnection([
                'driver' => 'pdo_sqlite',
                'path' => self::string($config, 'path'),
            ]),
            'pdo_mysql' => DriverManager::getConnection([
                'driver' => 'pdo_mysql',
                'host' => self::string($config, 'host'),
                'port' => self::int($config, 'port', 3306),
                'dbname' => self::string($config, 'dbname'),
                'user' => self::string($config, 'user'),
                'password' => self::string($config, 'password', allowEmpty: true),
                'charset' => 'utf8mb4',
            ]),
            default => throw new UnexpectedValueException('database.driver deve essere "pdo_sqlite" o "pdo_mysql"'),
        };
    }

    /**
     * @param array<string, mixed> $config
     */
    private static function string(array $config, string $key, bool $allowEmpty = false): string
    {
        $value = $config[$key] ?? null;
        if (!is_string($value) || (!$allowEmpty && $value === '')) {
            throw new UnexpectedValueException("database.{$key} mancante o non valido");
        }

        return $value;
    }

    /**
     * @param array<string, mixed> $config
     */
    private static function int(array $config, string $key, int $default): int
    {
        $value = $config[$key] ?? $default;
        if (!is_int($value)) {
            throw new UnexpectedValueException("database.{$key} deve essere un intero");
        }

        return $value;
    }
}

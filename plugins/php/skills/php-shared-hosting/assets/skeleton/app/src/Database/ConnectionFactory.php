<?php

declare(strict_types=1);

namespace App\Database;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use UnexpectedValueException;

/**
 * Translates the configuration into typed DBAL parameters. The connection is lazy:
 * the database is contacted only on the first query.
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
            default => throw new UnexpectedValueException('database.driver must be "pdo_sqlite" or "pdo_mysql"'),
        };
    }

    /**
     * @param array<string, mixed> $config
     */
    private static function string(array $config, string $key, bool $allowEmpty = false): string
    {
        $value = $config[$key] ?? null;
        if (!is_string($value) || (!$allowEmpty && $value === '')) {
            throw new UnexpectedValueException("database.{$key} missing or invalid");
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
            throw new UnexpectedValueException("database.{$key} must be an integer");
        }

        return $value;
    }
}

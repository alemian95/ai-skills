<?php

declare(strict_types=1);

namespace App\Database;

use DateTimeImmutable;
use DateTimeZone;
use Doctrine\DBAL\Connection;
use RuntimeException;
use UnexpectedValueException;

/**
 * Applies the migrations in order, only once. Idempotent and protected by a lock:
 * it can be run from cron at regular intervals with no effect when there is nothing to do.
 */
final readonly class Migrator
{
    private const string TABLE = 'schema_migrations';

    public function __construct(
        private Connection $db,
        private string $directory,
        private string $lockFile,
    ) {}

    /**
     * @return list<string> versions applied in this run
     */
    public function migrate(): array
    {
        $lock = fopen($this->lockFile, 'c');
        if ($lock === false || !flock($lock, LOCK_EX | LOCK_NB)) {
            throw new RuntimeException('Another migration run is in progress');
        }

        try {
            $this->ensureTable();
            $applied = [];
            foreach ($this->pending() as $version => $file) {
                $migration = require $file;
                if (!$migration instanceof Migration) {
                    throw new UnexpectedValueException("{$file} must return an instance of " . Migration::class);
                }

                // Note: in MySQL DDL statements (CREATE/ALTER) implicitly close the transaction.
                // A migration that fails halfway must be fixed with a new migration, not repeated.
                $migration->up($this->db);
                $this->db->insert(self::TABLE, [
                    'version' => $version,
                    'applied_at' => new DateTimeImmutable('now', new DateTimeZone('UTC'))->format('Y-m-d H:i:s'),
                ]);
                $applied[] = $version;
            }

            return $applied;
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    /**
     * @return array<string, bool> version => already applied
     */
    public function status(): array
    {
        $this->ensureTable();
        $applied = $this->applied();
        $status = [];
        foreach ($this->available() as $version => $file) {
            $status[$version] = in_array($version, $applied, true);
        }

        return $status;
    }

    private function ensureTable(): void
    {
        // SQL common to SQLite and MySQL; 191 characters stay below the utf8mb4 index limit.
        $this->db->executeStatement(sprintf(
            'CREATE TABLE IF NOT EXISTS %s (version VARCHAR(191) NOT NULL PRIMARY KEY, applied_at VARCHAR(19) NOT NULL)',
            self::TABLE,
        ));
    }

    /**
     * @return list<string>
     */
    private function applied(): array
    {
        $versions = $this->db->fetchFirstColumn(sprintf('SELECT version FROM %s', self::TABLE));

        return array_values(array_filter($versions, is_string(...)));
    }

    /**
     * @return array<string, string> version => file, in order
     */
    private function available(): array
    {
        $files = glob($this->directory . '/*.php') ?: [];
        sort($files, SORT_STRING);
        $versions = [];
        foreach ($files as $file) {
            $versions[basename($file, '.php')] = $file;
        }

        return $versions;
    }

    /**
     * @return array<string, string>
     */
    private function pending(): array
    {
        $applied = $this->applied();

        return array_filter(
            $this->available(),
            static fn(string $version): bool => !in_array($version, $applied, true),
            ARRAY_FILTER_USE_KEY,
        );
    }
}

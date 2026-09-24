<?php

declare(strict_types=1);

namespace App\Tests\Database;

use App\Database\Migrator;
use Doctrine\DBAL\DriverManager;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(Migrator::class)]
final class MigratorTest extends TestCase
{
    #[Test]
    public function it_applies_each_migration_once(): void
    {
        $db = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
        $lock = tempnam(sys_get_temp_dir(), 'migrate');
        self::assertIsString($lock);
        $migrator = new Migrator($db, dirname(__DIR__, 2) . '/migrations', $lock);

        self::assertSame(['20260923_0001_create_contact_messages' => false], $migrator->status());
        self::assertSame(['20260923_0001_create_contact_messages'], $migrator->migrate());
        self::assertSame([], $migrator->migrate(), 'seconda esecuzione senza effetti');
        self::assertSame(['20260923_0001_create_contact_messages' => true], $migrator->status());
        self::assertTrue($db->createSchemaManager()->tablesExist(['contact_messages']));

        unlink($lock);
    }
}

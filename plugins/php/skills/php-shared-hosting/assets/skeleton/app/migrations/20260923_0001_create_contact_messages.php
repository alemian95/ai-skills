<?php

declare(strict_types=1);

use App\Database\Migration;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\PrimaryKeyConstraint;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Types\Types;

// DBAL's schema API generates correct SQL for SQLite (development) and MySQL (production).
// If the project uses a single database, hand-written SQL with $db->executeStatement() is fine too.
return new class implements Migration {
    public function up(Connection $db): void
    {
        $db->createSchemaManager()->createTable(
            Table::editor()
                ->setUnquotedName('contact_messages')
                ->setColumns(
                    Column::editor()->setUnquotedName('id')->setTypeName(Types::INTEGER)->setAutoincrement(true)->create(),
                    Column::editor()->setUnquotedName('name')->setTypeName(Types::STRING)->setLength(100)->create(),
                    Column::editor()->setUnquotedName('email')->setTypeName(Types::STRING)->setLength(254)->create(),
                    Column::editor()->setUnquotedName('message')->setTypeName(Types::TEXT)->create(),
                    Column::editor()->setUnquotedName('created_at')->setTypeName(Types::DATETIME_IMMUTABLE)->create(),
                )
                ->setPrimaryKeyConstraint(PrimaryKeyConstraint::editor()->setUnquotedColumnNames('id')->create())
                ->create(),
        );
    }
};

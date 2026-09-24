<?php

declare(strict_types=1);

use App\Database\Migration;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\PrimaryKeyConstraint;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Types\Types;

// L'API di schema di DBAL genera SQL corretto per SQLite (sviluppo) e MySQL (produzione).
// Se il progetto usa un solo database, anche SQL scritto a mano con $db->executeStatement() va bene.
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

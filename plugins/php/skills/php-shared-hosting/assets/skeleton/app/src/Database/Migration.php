<?php

declare(strict_types=1);

namespace App\Database;

use Doctrine\DBAL\Connection;

/**
 * A file in migrations/ returns an instance of this interface (anonymous class).
 * The file name (without .php) is the version: use the format YYYYMMDD_NNNN_description.
 */
interface Migration
{
    public function up(Connection $db): void;
}

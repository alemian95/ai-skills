<?php

declare(strict_types=1);

namespace App\Database;

use Doctrine\DBAL\Connection;

/**
 * Un file in migrations/ restituisce un'istanza di questa interfaccia (classe anonima).
 * Il nome del file (senza .php) è la versione: usa il formato AAAAMMGG_NNNN_descrizione.
 */
interface Migration
{
    public function up(Connection $db): void;
}

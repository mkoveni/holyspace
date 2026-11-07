<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Persistence;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Tools\DsnParser;

/**
 * Builds the single DBAL connection shared by every store. DBAL only: there is
 * no ORM, entity manager or unit-of-work tracking.
 */
final class ConnectionFactory
{
    public static function create(string $databaseUrl): Connection
    {
        $params = new DsnParser(['postgresql' => 'pdo_pgsql'])->parse($databaseUrl);

        return DriverManager::getConnection($params);
    }
}

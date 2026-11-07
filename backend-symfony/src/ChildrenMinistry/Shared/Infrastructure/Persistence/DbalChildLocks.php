<?php

declare(strict_types=1);

namespace App\ChildrenMinistry\Shared\Infrastructure\Persistence;

use App\ChildrenMinistry\Shared\Application\Contract\ChildLocks;
use Doctrine\DBAL\Connection;

final readonly class DbalChildLocks implements ChildLocks
{
    public function __construct(private Connection $connection)
    {
    }

    public function lock(string $childId): void
    {
        $this->connection->executeStatement('INSERT INTO children_child_guards (child_id) VALUES (?) ON CONFLICT DO NOTHING', [$childId]);
        $this->connection->fetchOne('SELECT child_id FROM children_child_guards WHERE child_id = ? FOR UPDATE', [$childId]);
    }
}

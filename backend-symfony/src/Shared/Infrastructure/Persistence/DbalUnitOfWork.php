<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Persistence;

use App\Shared\Application\UnitOfWork;
use Doctrine\DBAL\Connection;

final readonly class DbalUnitOfWork implements UnitOfWork
{
    public function __construct(private Connection $connection)
    {
    }

    public function execute(callable $operation): mixed
    {
        return $this->connection->transactional(static fn (): mixed => $operation());
    }
}

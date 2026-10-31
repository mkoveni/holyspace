<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Audit;

use Doctrine\DBAL\Driver\Middleware\AbstractStatementMiddleware;
use Doctrine\DBAL\Driver\Result;
use Doctrine\DBAL\Driver\Statement;
use Symfony\Component\DependencyInjection\Attribute\Exclude;

#[Exclude]
final class AuditStatement extends AbstractStatementMiddleware
{
    public function __construct(Statement $statement, private readonly AuditConnection $connection)
    {
        parent::__construct($statement);
    }

    public function execute(): Result
    {
        return $this->connection->attributed(fn () => parent::execute());
    }
}

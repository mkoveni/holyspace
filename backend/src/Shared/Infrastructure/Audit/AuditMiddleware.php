<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Audit;

use Doctrine\Bundle\DoctrineBundle\Attribute\AsMiddleware;
use Doctrine\DBAL\Driver;
use Doctrine\DBAL\Driver\Connection;
use Doctrine\DBAL\Driver\Middleware;
use Doctrine\DBAL\Driver\Middleware\AbstractDriverMiddleware;
use SensitiveParameter;

#[AsMiddleware]
final readonly class AuditMiddleware implements Middleware
{
    public function __construct(private AuditIdentity $identity)
    {
    }

    public function wrap(Driver $driver): Driver
    {
        return new class($driver, $this->identity) extends AbstractDriverMiddleware {
            public function __construct(Driver $driver, private readonly AuditIdentity $identity)
            {
                parent::__construct($driver);
            }

            public function connect(#[SensitiveParameter] array $params): Connection
            {
                $connection = parent::connect($params);
                if (($params['driver'] ?? '') !== 'pdo_pgsql') {
                    return $connection;
                }

                return new AuditConnection($connection, $this->identity);
            }
        };
    }
}

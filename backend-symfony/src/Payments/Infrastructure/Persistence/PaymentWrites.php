<?php

declare(strict_types=1);

namespace App\Payments\Infrastructure\Persistence;

use App\Payments\Domain\PaymentException;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception\DriverException;
use Doctrine\DBAL\ParameterType;

/** Runs Payments writes, translating integrity violations (unique keys, evidence guards) into Java's 409 message. */
final readonly class PaymentWrites
{
    public function __construct(private Connection $connection)
    {
    }

    /**
     * @param list<mixed>                       $params
     * @param array<int<0, max>, ParameterType> $types
     */
    public function execute(string $sql, array $params, array $types = []): int
    {
        try {
            return (int) $this->connection->executeStatement($sql, $params, $types);
        } catch (DriverException $error) {
            if (str_starts_with((string) $error->getSQLState(), '23')) {
                throw PaymentException::evidenceConflict();
            }
            throw $error;
        }
    }
}

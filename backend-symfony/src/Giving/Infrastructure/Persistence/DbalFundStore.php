<?php

declare(strict_types=1);

namespace App\Giving\Infrastructure\Persistence;

use App\Giving\Domain\Fund;
use App\Giving\Domain\GivingException;
use App\Giving\Domain\Repository\FundRepository;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\DBAL\ParameterType;

final readonly class DbalFundStore implements FundRepository
{
    public function __construct(private Connection $connection)
    {
    }

    public function findForUpdate(string $id): ?Fund
    {
        $row = $this->connection->createQueryBuilder()
            ->select('id', 'name', 'description', 'active', 'version')
            ->from('giving_funds')
            ->where('id = :id')
            ->setParameter('id', $id)
            ->forUpdate()
            ->fetchAssociative();

        return false === $row ? null : Fund::reconstitute(
            (string) $row['id'],
            (string) $row['name'],
            (string) $row['description'],
            (bool) $row['active'],
            (int) $row['version'],
        );
    }

    public function createdBy(string $requestId, string $hash): ?string
    {
        $saved = $this->connection->createQueryBuilder()
            ->select('request_hash')
            ->from('giving_funds')
            ->where('id = :id')
            ->setParameter('id', $requestId)
            ->fetchOne();
        if (false === $saved) {
            return null;
        }
        if ($saved !== $hash) {
            throw GivingException::conflict('This request identifier was already used for different details.');
        }

        return $requestId;
    }

    public function insert(Fund $fund, string $hash): bool
    {
        // ON CONFLICT covers both the primary key (a concurrent retry) and giving_fund_name_unique.
        return 1 === $this->connection->executeStatement(
            'INSERT INTO giving_funds (id, name, description, active, version, request_hash) VALUES (:id, :name, :description, :active, :version, :hash) ON CONFLICT DO NOTHING',
            [
                'id' => $fund->id,
                'name' => $fund->name,
                'description' => $fund->description,
                'active' => $fund->active,
                'version' => $fund->version,
                'hash' => $hash,
            ],
            ['active' => ParameterType::BOOLEAN, 'version' => ParameterType::INTEGER],
        );
    }

    public function save(Fund $fund): void
    {
        try {
            $changed = $this->connection->update('giving_funds', [
                'name' => $fund->name,
                'description' => $fund->description,
                'active' => $fund->active,
                'version' => $fund->version,
            ], ['id' => $fund->id, 'version' => $fund->version - 1], ['active' => ParameterType::BOOLEAN]);
        } catch (UniqueConstraintViolationException) {
            throw GivingException::evidenceConflict();
        }
        if (1 !== $changed) {
            throw GivingException::conflict('The fund changed. Refresh before trying again.');
        }
    }

    public function offerOnline(string $fundId, bool $enabled): void
    {
        $this->connection->executeStatement(
            'INSERT INTO giving_online_funds (fund_id, enabled) VALUES (?, ?) ON CONFLICT (fund_id) DO UPDATE SET enabled = EXCLUDED.enabled',
            [$fundId, $enabled],
            [1 => ParameterType::BOOLEAN],
        );
    }
}

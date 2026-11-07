<?php

declare(strict_types=1);

namespace App\IdentityAccess\Infrastructure\Persistence;

use App\IdentityAccess\Application\Contract\AccountReader;
use App\IdentityAccess\Application\Contract\AccountSummary;
use App\IdentityAccess\Domain\Account;
use App\IdentityAccess\Domain\Repository\AccountRepository;
use App\Shared\Domain\StaleRevisionException;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Query\QueryBuilder;

final readonly class DbalAccountStore implements AccountReader, AccountRepository
{
    public function __construct(private Connection $connection)
    {
    }

    public function find(string $id): ?AccountSummary
    {
        $row = $this->select()->where('id = :id')->setParameter('id', $id)->fetchAssociative();

        return false === $row ? null : AccountRecordMapper::toSummary($row);
    }

    public function findByLogin(string $identifier): ?AccountSummary
    {
        $row = $this->select()
            ->where('username = :identifier OR email = :identifier')
            ->setParameter('identifier', $identifier)
            ->fetchAssociative();

        return false === $row ? null : AccountRecordMapper::toSummary($row);
    }

    public function findForUpdate(string $id): ?Account
    {
        $row = $this->select()->where('id = :id')->setParameter('id', $id)->forUpdate()->fetchAssociative();

        return false === $row ? null : AccountRecordMapper::toDomain($row);
    }

    public function search(string $search, int $limit): array
    {
        $pattern = '%'.addcslashes($search, '\\%_').'%';
        $rows = $this->select()
            ->where('username LIKE :pattern OR email LIKE :pattern OR display_name ILIKE :pattern')
            ->setParameter('pattern', $pattern)
            ->orderBy('display_name')
            ->addOrderBy('id')
            ->setMaxResults($limit)
            ->fetchAllAssociative();

        return array_values(array_map(AccountRecordMapper::toSummary(...), $rows));
    }

    public function any(): bool
    {
        return false !== $this->connection->createQueryBuilder()->select('1')->from('user_accounts')->setMaxResults(1)->fetchOne();
    }

    public function add(Account $account): void
    {
        $this->connection->insert('user_accounts', AccountRecordMapper::toRow($account));
    }

    public function save(Account $account): void
    {
        $row = AccountRecordMapper::toRow($account);
        unset($row['id'], $row['username'], $row['created_at']); // identity, username and creation time never change

        $changed = $this->connection->update('user_accounts', $row, ['id' => $account->id, 'version' => $account->revision->previousVersion()]);
        if (1 !== $changed) {
            throw new StaleRevisionException();
        }
    }

    private function select(): QueryBuilder
    {
        return $this->connection->createQueryBuilder()->select('*')->from('user_accounts');
    }
}

<?php

declare(strict_types=1);

namespace App\Giving\Infrastructure\Persistence;

use App\Giving\Application\Contract\DonorLinkReader;
use App\Giving\Application\Contract\DonorLinkView;
use App\Giving\Application\Contract\OnlineFund;
use App\Giving\Application\Contract\OnlineFundDirectory;
use App\Shared\Infrastructure\Persistence\Timestamps;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Query\QueryBuilder;

/** Donor links and the online fund catalogue as read models. */
final readonly class DbalDonorAccessReader implements DonorLinkReader, OnlineFundDirectory
{
    public function __construct(private Connection $connection)
    {
    }

    public function link(string $accountId): ?DonorLinkView
    {
        $row = $this->linkQuery()->where('account_id = :account')->setParameter('account', $accountId)->fetchAssociative();

        return false === $row ? null : self::view($row);
    }

    public function links(int $limit, int $offset): array
    {
        return array_values(array_map(self::view(...), $this->linkQuery()
            ->orderBy('verified_at', 'DESC')
            ->addOrderBy('account_id')
            ->setMaxResults($limit)
            ->setFirstResult($offset)
            ->fetchAllAssociative()));
    }

    public function funds(bool $management): array
    {
        $query = $this->connection->createQueryBuilder()
            ->select('f.id', 'f.name', 'f.description', 'f.active', 'f.version', 'COALESCE(o.enabled, FALSE) AS enabled')
            ->from('giving_funds', 'f')
            ->leftJoin('f', 'giving_online_funds', 'o', 'o.fund_id = f.id')
            ->orderBy('f.name')
            ->addOrderBy('f.id')
            ->setMaxResults(self::CATALOGUE_LIMIT + 1);
        if (!$management) {
            $query->where('f.active AND o.enabled');
        }

        return array_values(array_map(static fn (array $row): OnlineFund => new OnlineFund(
            (string) $row['id'],
            (string) $row['name'],
            (string) $row['description'],
            (bool) $row['active'],
            (bool) $row['enabled'],
            (int) $row['version'],
        ), $query->fetchAllAssociative()));
    }

    public function enabled(string $fundId): bool
    {
        return false !== $this->connection->createQueryBuilder()
            ->select('1')
            ->from('giving_funds', 'f')
            ->innerJoin('f', 'giving_online_funds', 'o', 'o.fund_id = f.id')
            ->where('f.id = :fund')
            ->andWhere('f.active AND o.enabled')
            ->setParameter('fund', $fundId)
            ->fetchOne();
    }

    private function linkQuery(): QueryBuilder
    {
        return $this->connection->createQueryBuilder()
            ->select('account_id', 'person_id', 'active', 'version', 'verified_by', 'verified_at')
            ->from('giving_donor_links');
    }

    /** @param array<string, mixed> $row */
    private static function view(array $row): DonorLinkView
    {
        return new DonorLinkView(
            (string) $row['account_id'],
            (string) $row['person_id'],
            (bool) $row['active'],
            (int) $row['version'],
            (string) $row['verified_by'],
            Timestamps::fromDatabase((string) $row['verified_at']),
        );
    }
}

<?php

declare(strict_types=1);

namespace App\IdentityAccess\Infrastructure\Persistence;

use App\IdentityAccess\Application\Contract\MemberLinkReader;
use App\IdentityAccess\Application\Contract\MemberLinkSummary;
use App\IdentityAccess\Domain\MemberLink;
use App\IdentityAccess\Domain\Repository\MemberLinkRepository;
use App\Shared\Infrastructure\Persistence\Timestamps;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\Query\QueryBuilder;
use Symfony\Component\Uid\Uuid;

final readonly class DbalMemberLinkStore implements MemberLinkReader, MemberLinkRepository
{
    public function __construct(private Connection $connection)
    {
    }

    public function memberLink(string $accountId): ?MemberLinkSummary
    {
        $row = $this->select()->where('account_id = :account')->setParameter('account', $accountId)->fetchAssociative();

        return false === $row ? null : self::summary($row);
    }

    public function memberLinks(int $limit, int $offset): array
    {
        $rows = $this->select()
            ->orderBy('verified_at', 'DESC')
            ->addOrderBy('account_id')
            ->setMaxResults($limit)
            ->setFirstResult($offset)
            ->fetchAllAssociative();

        return array_values(array_map(self::summary(...), $rows));
    }

    public function lock(string $accountId): ?MemberLink
    {
        $row = $this->select()->where('account_id = :account')->setParameter('account', $accountId)->forUpdate()->fetchAssociative();
        if (false === $row) {
            return null;
        }
        $summary = self::summary($row);

        return MemberLink::reconstitute($summary->accountId, $summary->personId, $summary->active, $summary->version, $summary->verifiedBy, $summary->verifiedAt);
    }

    public function save(MemberLink $link, string $evidence): void
    {
        $verifiedAt = Timestamps::toDatabase($link->verifiedAt);
        $this->connection->executeStatement(
            'INSERT INTO identity_member_links (account_id, person_id, active, version, verified_by, verified_at) VALUES (?, ?, ?, ?, ?, ?)
             ON CONFLICT (account_id) DO UPDATE SET active = EXCLUDED.active, version = EXCLUDED.version,
                 verified_by = EXCLUDED.verified_by, verified_at = EXCLUDED.verified_at',
            [$link->accountId, $link->personId, $link->active, $link->version, $link->verifiedBy, $verifiedAt],
            [2 => ParameterType::BOOLEAN],
        );
        $this->connection->insert('identity_member_link_history', [
            'id' => Uuid::v4()->toRfc4122(),
            'account_id' => $link->accountId,
            'person_id' => $link->personId,
            'active' => $link->active,
            'version' => $link->version,
            'actor_id' => $link->verifiedBy,
            'evidence' => $evidence,
            'occurred_at' => $verifiedAt,
        ], ['active' => ParameterType::BOOLEAN]);
    }

    /** @param array<string, mixed> $row */
    private static function summary(array $row): MemberLinkSummary
    {
        return new MemberLinkSummary(
            (string) $row['account_id'],
            (string) $row['person_id'],
            true === $row['active'] || 't' === $row['active'] || '1' === $row['active'],
            (int) $row['version'],
            (string) $row['verified_by'],
            Timestamps::fromDatabase((string) $row['verified_at']),
        );
    }

    private function select(): QueryBuilder
    {
        return $this->connection->createQueryBuilder()->select('*')->from('identity_member_links');
    }
}

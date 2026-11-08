<?php

declare(strict_types=1);

namespace App\Communities\Infrastructure\Persistence;

use App\Communities\Application\Contract\CommunityParticipation;
use App\Communities\Application\Contract\CommunityReader;
use App\Communities\Application\Contract\CommunityView;
use App\Communities\Application\Contract\FollowUpView;
use App\Communities\Application\Contract\HistoryEntry;
use App\Communities\Application\Contract\LeadershipView;
use App\Communities\Application\Contract\MembershipView;
use App\Communities\Application\Contract\MemberView;
use App\Communities\Application\Contract\ParticipationEntry;
use App\People\Application\Contract\PersonDirectory;
use App\Shared\Domain\CalendarDate;
use App\Shared\Infrastructure\Persistence\LikePattern;
use App\Shared\Infrastructure\Persistence\Timestamps;
use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\Query\QueryBuilder;

/** Staff directory read models over V18; person names come from People's published directory. */
final readonly class DbalCommunityReader implements CommunityReader, CommunityParticipation
{
    private const string UNAVAILABLE = 'Unavailable person';

    public function __construct(private Connection $connection, private PersonDirectory $people)
    {
    }

    public function community(string $id): ?CommunityView
    {
        $row = $this->connection->createQueryBuilder()->select('*')->from('communities', 'c')->where('c.id = :id')->setParameter('id', $id)->fetchAssociative();

        return false === $row ? null : self::toCommunity($row);
    }

    public function directory(?string $kind, string $search, bool $archived, ?string $scopedAccount, int $limit, int $offset, DateTimeImmutable $at): array
    {
        $rows = $this->directoryQuery($kind, $search, $archived, $scopedAccount, $at)
            ->select('c.*')
            ->orderBy('c.name')
            ->addOrderBy('c.id')
            ->setMaxResults($limit)
            ->setFirstResult($offset)
            ->fetchAllAssociative();

        return array_values(array_map(self::toCommunity(...), $rows));
    }

    public function directoryCount(?string $kind, string $search, bool $archived, ?string $scopedAccount, DateTimeImmutable $at): int
    {
        return (int) $this->directoryQuery($kind, $search, $archived, $scopedAccount, $at)->select('count(*)')->fetchOne();
    }

    public function leads(string $communityId, string $accountId, DateTimeImmutable $at): bool
    {
        $query = $this->connection->createQueryBuilder()->select('1')->from('communities', 'c')->where('c.id = :id')->setParameter('id', $communityId);
        $this->scope($query, $accountId, $at);

        return false !== $query->fetchOne();
    }

    public function membership(string $id): ?MembershipView
    {
        $row = $this->connection->createQueryBuilder()->select('*')->from('community_memberships')->where('id = :id')->setParameter('id', $id)->fetchAssociative();

        return false === $row ? null : self::toMembership($row);
    }

    public function members(string $communityId, bool $ended, int $limit, int $offset): array
    {
        $query = $this->connection->createQueryBuilder()
            ->select('*')
            ->from('community_memberships')
            ->where('community_id = :community')
            ->setParameter('community', $communityId)
            ->orderBy('started_at', 'DESC')
            ->addOrderBy('id')
            ->setMaxResults($limit)
            ->setFirstResult($offset);
        if (!$ended) {
            $query->andWhere('ended_at IS NULL');
        }
        $memberships = array_map(self::toMembership(...), $query->fetchAllAssociative());
        $names = $this->names(array_values(array_map(static fn (MembershipView $m): string => $m->personId, $memberships)));

        return array_values(array_map(static fn (MembershipView $m): MemberView => new MemberView($m, $names[$m->personId] ?? self::UNAVAILABLE), $memberships));
    }

    public function memberCount(string $communityId, bool $ended): int
    {
        $query = $this->connection->createQueryBuilder()
            ->select('count(*)')
            ->from('community_memberships')
            ->where('community_id = :community')
            ->setParameter('community', $communityId);
        if (!$ended) {
            $query->andWhere('ended_at IS NULL');
        }

        return (int) $query->fetchOne();
    }

    public function leadership(string $communityId, int $limit, int $offset): array
    {
        $rows = $this->connection->createQueryBuilder()
            ->select('l.*', 'm.person_id')
            ->from('community_leadership', 'l')
            ->innerJoin('l', 'community_memberships', 'm', 'm.id = l.membership_id')
            ->where('l.community_id = :community')
            ->setParameter('community', $communityId)
            ->orderBy('l.started_at', 'DESC')
            ->addOrderBy('l.id')
            ->setMaxResults($limit)
            ->setFirstResult($offset)
            ->fetchAllAssociative();

        return array_values(array_map(static fn (array $row): LeadershipView => new LeadershipView(
            (string) $row['id'],
            (string) $row['membership_id'],
            (string) $row['community_id'],
            null === $row['account_id'] ? null : (string) $row['account_id'],
            (string) $row['role'],
            Timestamps::fromDatabase((string) $row['started_at']),
            null === $row['ended_at'] ? null : Timestamps::fromDatabase((string) $row['ended_at']),
            (int) $row['version'],
            (string) $row['person_id'],
        ), $rows));
    }

    public function history(string $communityId, int $limit, int $offset): array
    {
        $rows = $this->connection->createQueryBuilder()
            ->select('*')
            ->from('community_history')
            ->where('community_id = :community')
            ->setParameter('community', $communityId)
            ->orderBy('occurred_at', 'DESC')
            ->addOrderBy('id')
            ->setMaxResults($limit)
            ->setFirstResult($offset)
            ->fetchAllAssociative();

        return array_values(array_map(static fn (array $row): HistoryEntry => new HistoryEntry(
            (string) $row['id'],
            (string) $row['target_id'],
            (string) $row['action'],
            (string) $row['reason'],
            (string) $row['actor_id'],
            Timestamps::fromDatabase((string) $row['occurred_at']),
        ), $rows));
    }

    public function followUps(string $communityId, bool $completed, int $limit, int $offset): array
    {
        $query = $this->connection->createQueryBuilder()
            ->select('*')
            ->from('community_followups')
            ->where('community_id = :community')
            ->setParameter('community', $communityId)
            ->orderBy('due_on')
            ->addOrderBy('id')
            ->setMaxResults($limit)
            ->setFirstResult($offset);
        if (!$completed) {
            $query->andWhere("status = 'OPEN'");
        }
        $rows = $query->fetchAllAssociative();
        $names = $this->names(array_values(array_map(static fn (array $row): string => (string) $row['person_id'], $rows)));

        return array_values(array_map(static fn (array $row): FollowUpView => new FollowUpView(
            (string) $row['id'],
            (string) $row['community_id'],
            (string) $row['person_id'],
            $names[(string) $row['person_id']] ?? self::UNAVAILABLE,
            (string) $row['note'],
            CalendarDate::parse((string) $row['due_on']),
            (string) $row['status'],
            (int) $row['version'],
        ), $rows));
    }

    public function forPerson(string $personId, int $limit, int $offset): array
    {
        $rows = $this->connection->createQueryBuilder()
            ->select('c.id AS community_id', 'c.kind', 'c.name', 'm.id AS membership_id', 'm.started_at', 'm.ended_at')
            ->from('community_memberships', 'm')
            ->innerJoin('m', 'communities', 'c', 'c.id = m.community_id')
            ->where('m.person_id = :person')
            ->setParameter('person', $personId)
            ->orderBy('m.started_at', 'DESC')
            ->addOrderBy('m.id')
            ->setMaxResults($limit)
            ->setFirstResult($offset)
            ->fetchAllAssociative();

        return array_values(array_map(static fn (array $row): ParticipationEntry => new ParticipationEntry(
            (string) $row['community_id'],
            (string) $row['kind'],
            (string) $row['name'],
            (string) $row['membership_id'],
            Timestamps::fromDatabase((string) $row['started_at']),
            null === $row['ended_at'] ? null : Timestamps::fromDatabase((string) $row['ended_at']),
        ), $rows));
    }

    private function directoryQuery(?string $kind, string $search, bool $archived, ?string $account, DateTimeImmutable $at): QueryBuilder
    {
        $query = $this->connection->createQueryBuilder()->from('communities', 'c');
        $this->scope($query, $account, $at);
        if (null !== $kind) {
            $query->andWhere('c.kind = :kind')->setParameter('kind', $kind);
        }
        if (!$archived) {
            $query->andWhere('c.active');
        }
        if ('' !== $search) {
            // communities_name_trgm_idx (V36) serves this.
            $query->andWhere('c.name ILIKE :search')->setParameter('search', LikePattern::contains($search));
        }

        return $query;
    }

    /** Limits to active communities the account leads now, through a current appointment on a current membership. */
    private function scope(QueryBuilder $query, ?string $account, DateTimeImmutable $at): void
    {
        if (null === $account) {
            return;
        }
        $query->andWhere('c.active')->andWhere(
            'c.id IN (SELECT l.community_id FROM community_leadership l JOIN community_memberships m ON m.id = l.membership_id
             WHERE l.account_id = :scope_account AND l.started_at <= :scope_at AND (l.ended_at IS NULL OR l.ended_at > :scope_at)
             AND m.started_at <= :scope_at AND (m.ended_at IS NULL OR m.ended_at > :scope_at))',
        )
            ->setParameter('scope_account', $account)
            ->setParameter('scope_at', Timestamps::toDatabase($at), ParameterType::STRING);
    }

    /**
     * @param list<string> $ids
     *
     * @return array<string, string>
     */
    private function names(array $ids): array
    {
        $names = [];
        foreach ($this->people->getAll(array_values(array_unique($ids))) as $person) {
            $names[$person->id] = $person->name;
        }

        return $names;
    }

    /** @param array<string, mixed> $row */
    private static function toCommunity(array $row): CommunityView
    {
        return new CommunityView(
            (string) $row['id'],
            (string) $row['kind'],
            (string) $row['name'],
            (string) $row['description'],
            null === $row['parent_id'] ? null : (string) $row['parent_id'],
            (string) $row['campus'],
            (bool) $row['active'],
            (int) $row['version'],
        );
    }

    /** @param array<string, mixed> $row */
    private static function toMembership(array $row): MembershipView
    {
        return new MembershipView(
            (string) $row['id'],
            (string) $row['community_id'],
            (string) $row['person_id'],
            Timestamps::fromDatabase((string) $row['started_at']),
            null === $row['ended_at'] ? null : Timestamps::fromDatabase((string) $row['ended_at']),
            (int) $row['version'],
        );
    }
}

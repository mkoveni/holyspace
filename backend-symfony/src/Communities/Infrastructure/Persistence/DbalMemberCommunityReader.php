<?php

declare(strict_types=1);

namespace App\Communities\Infrastructure\Persistence;

use App\Communities\Application\Contract\LeaderEntry;
use App\Communities\Application\Contract\MemberCommunity;
use App\Communities\Application\Contract\MemberCommunityReader;
use App\Communities\Application\Contract\MemberMeetingRow;
use App\Shared\Infrastructure\Persistence\Timestamps;
use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Query\QueryBuilder;

/** Java `JooqMemberCommunityStore`: own-membership projections over V18, joined on the caller's current membership. */
final readonly class DbalMemberCommunityReader implements MemberCommunityReader
{
    public function __construct(private Connection $connection)
    {
    }

    public function communities(string $personId, ?string $kind, DateTimeImmutable $at, int $limit, int $offset): array
    {
        $query = $this->communityQuery($personId, $at)->orderBy('c.name')->addOrderBy('c.id')->setMaxResults($limit)->setFirstResult($offset);
        if (null !== $kind) {
            $query->andWhere('c.kind = :kind')->setParameter('kind', $kind);
        }

        return array_values(array_map(self::toCommunity(...), $query->fetchAllAssociative()));
    }

    public function community(string $personId, string $communityId, DateTimeImmutable $at): ?MemberCommunity
    {
        $row = $this->communityQuery($personId, $at)->andWhere('c.id = :id')->setParameter('id', $communityId)->fetchAssociative();

        return false === $row ? null : self::toCommunity($row);
    }

    public function leaders(string $personId, string $communityId, DateTimeImmutable $at, int $limit, int $offset): array
    {
        $query = $this->connection->createQueryBuilder()
            ->select('m.person_id', 'l.role')
            ->from('community_leadership', 'l')
            ->join('l', 'community_memberships', 'm', 'm.id = l.membership_id')
            ->join('l', 'communities', 'c', 'c.id = l.community_id')
            ->join('c', 'community_memberships', 'own', 'own.community_id = c.id')
            ->andWhere('c.id = :community')
            ->andWhere('l.started_at <= :at')
            ->andWhere('(l.ended_at IS NULL OR l.ended_at > :at)')
            ->andWhere('m.started_at <= :at')
            ->andWhere('(m.ended_at IS NULL OR m.ended_at > :at)')
            ->setParameter('community', $communityId)
            ->orderBy('l.role')
            ->addOrderBy('l.id')
            ->setMaxResults($limit)
            ->setFirstResult($offset);
        $this->current($query, $personId, $at);

        return array_values(array_map(static fn (array $r): LeaderEntry => new LeaderEntry((string) $r['person_id'], (string) $r['role']), $query->fetchAllAssociative()));
    }

    public function meetings(string $personId, ?string $communityId, DateTimeImmutable $from, DateTimeImmutable $until, int $limit, int $offset): array
    {
        $query = $this->meetingQuery($personId, $from)
            ->andWhere('mt.scheduled_at >= :from')
            ->andWhere('mt.scheduled_at < :until')
            ->setParameter('from', Timestamps::toDatabase($from))
            ->setParameter('until', Timestamps::toDatabase($until))
            ->orderBy('mt.scheduled_at')
            ->addOrderBy('mt.id')
            ->setMaxResults($limit)
            ->setFirstResult($offset);
        if (null !== $communityId) {
            $query->andWhere('c.id = :community')->setParameter('community', $communityId);
        }

        return array_values(array_map(self::toMeeting(...), $query->fetchAllAssociative()));
    }

    public function meeting(string $personId, string $meetingId, DateTimeImmutable $at): ?MemberMeetingRow
    {
        $row = $this->meetingQuery($personId, $at)->andWhere('mt.id = :id')->setParameter('id', $meetingId)->fetchAssociative();

        return false === $row ? null : self::toMeeting($row);
    }

    /** The caller's membership `own` covers the instant and the community `c` is active. */
    private function current(QueryBuilder $query, string $personId, DateTimeImmutable $at): void
    {
        $query->andWhere('own.person_id = :person')
            ->andWhere('own.started_at <= :at')
            ->andWhere('(own.ended_at IS NULL OR own.ended_at > :at)')
            ->andWhere('c.active = TRUE')
            ->setParameter('person', $personId)
            ->setParameter('at', Timestamps::toDatabase($at));
    }

    private function communityQuery(string $personId, DateTimeImmutable $at): QueryBuilder
    {
        $query = $this->connection->createQueryBuilder()
            ->select('c.id', 'c.kind', 'c.name', 'c.description', 'c.campus', 'own.started_at', 'own_role.role', 'parent.name AS parent_name')
            ->from('communities', 'c')
            ->join('c', 'community_memberships', 'own', 'own.community_id = c.id')
            ->leftJoin('own', 'community_leadership', 'own_role', 'own_role.membership_id = own.id AND own_role.started_at <= :at AND (own_role.ended_at IS NULL OR own_role.ended_at > :at)')
            ->leftJoin('c', 'communities', 'parent', 'parent.id = c.parent_id AND parent.active = TRUE');
        $this->current($query, $personId, $at);

        return $query;
    }

    private function meetingQuery(string $personId, DateTimeImmutable $at): QueryBuilder
    {
        $query = $this->connection->createQueryBuilder()
            ->select('mt.id', 'mt.community_id', 'mt.occurrence_id', 'mt.status', 'mt.roster_frozen', 'c.name AS community_name', 'r.status AS attendance')
            ->from('community_meetings', 'mt')
            ->join('mt', 'communities', 'c', 'c.id = mt.community_id')
            ->join('c', 'community_memberships', 'own', 'own.community_id = c.id')
            ->leftJoin('mt', 'community_roster', 'r', 'r.meeting_id = mt.id AND r.person_id = :person')
            ->andWhere('(mt.scheduled_at >= own.started_at OR r.id IS NOT NULL)');
        $this->current($query, $personId, $at);

        return $query;
    }

    /** @param array<string, mixed> $row */
    private static function toCommunity(array $row): MemberCommunity
    {
        return new MemberCommunity(
            (string) $row['id'],
            (string) $row['kind'],
            (string) $row['name'],
            (string) $row['description'],
            (string) $row['campus'],
            null === $row['parent_name'] ? null : (string) $row['parent_name'],
            Timestamps::fromDatabase((string) $row['started_at']),
            null === $row['role'] ? 'MEMBER' : (string) $row['role'],
        );
    }

    /** @param array<string, mixed> $row */
    private static function toMeeting(array $row): MemberMeetingRow
    {
        $attendance = null !== $row['attendance'] ? (string) $row['attendance'] : ((bool) $row['roster_frozen'] ? 'NOT_LISTED' : 'NOT_PREPARED');

        return new MemberMeetingRow((string) $row['id'], (string) $row['community_id'], (string) $row['community_name'], (string) $row['occurrence_id'], (string) $row['status'], $attendance);
    }
}

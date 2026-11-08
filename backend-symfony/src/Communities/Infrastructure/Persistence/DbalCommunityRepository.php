<?php

declare(strict_types=1);

namespace App\Communities\Infrastructure\Persistence;

use App\Communities\Domain\Community;
use App\Communities\Domain\CommunityException;
use App\Communities\Domain\FollowUp;
use App\Communities\Domain\Leadership;
use App\Communities\Domain\Membership;
use App\Communities\Domain\Repository\CommunityRepository;
use App\Shared\Domain\CalendarDate;
use App\Shared\Infrastructure\Persistence\Timestamps;
use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception\DriverException;
use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\Query\QueryBuilder;

/**
 * Community aggregates over V18. Overlapping membership or leadership periods are also refused by the exclusion
 * constraints (`community_membership_period_excl`, `community_leadership_*_excl`); those and any other integrity
 * violation become Java's single conflict message.
 */
final readonly class DbalCommunityRepository implements CommunityRepository
{
    public function __construct(private Connection $connection)
    {
    }

    public function lockCommunity(string $id): ?Community
    {
        $row = $this->select('communities')->where('id = :id')->setParameter('id', $id)->forUpdate()->fetchAssociative();

        return false === $row ? null : Community::reconstitute(
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

    public function insertCommunity(Community $community): void
    {
        $this->guard(fn (): int|string => $this->connection->insert('communities', [
            'id' => $community->id,
            'kind' => $community->kind,
            'name' => $community->name,
            'description' => $community->description,
            'parent_id' => $community->parentId,
            'campus' => $community->campus,
            'active' => $community->active,
            'version' => $community->version,
        ], ['active' => ParameterType::BOOLEAN, 'version' => ParameterType::INTEGER]));
    }

    public function saveCommunity(Community $community): void
    {
        $this->saved($this->guard(fn (): int|string => $this->connection->update('communities', [
            'name' => $community->name,
            'description' => $community->description,
            'parent_id' => $community->parentId,
            'campus' => $community->campus,
            'active' => $community->active,
            'version' => $community->version,
        ], ['id' => $community->id, 'version' => $community->version - 1], ['active' => ParameterType::BOOLEAN, 'version' => ParameterType::INTEGER])));
    }

    public function findMembershipUnlocked(string $id): ?Membership
    {
        $row = $this->select('community_memberships')->where('id = :id')->setParameter('id', $id)->fetchAssociative();

        return false === $row ? null : self::membership($row);
    }

    public function currentMembership(string $communityId, string $personId, DateTimeImmutable $at): ?Membership
    {
        $row = $this->select('community_memberships')
            ->where('community_id = :community', 'person_id = :person', 'started_at <= :at', '(ended_at IS NULL OR ended_at > :at)')
            ->setParameter('community', $communityId)
            ->setParameter('person', $personId)
            ->setParameter('at', Timestamps::toDatabase($at))
            ->fetchAssociative();

        return false === $row ? null : self::membership($row);
    }

    public function membershipsAt(string $communityId, DateTimeImmutable $at, int $limit): array
    {
        $rows = $this->select('community_memberships')
            ->where('community_id = :community', 'started_at <= :at', '(ended_at IS NULL OR ended_at > :at)')
            ->setParameter('community', $communityId)
            ->setParameter('at', Timestamps::toDatabase($at))
            ->orderBy('id')
            ->setMaxResults($limit)
            ->fetchAllAssociative();

        return array_values(array_map(self::membership(...), $rows));
    }

    public function insertMembership(Membership $membership): void
    {
        $this->guard(fn (): int|string => $this->connection->insert('community_memberships', [
            'id' => $membership->id,
            'community_id' => $membership->communityId,
            'person_id' => $membership->personId,
            'started_at' => Timestamps::toDatabase($membership->startedAt),
            'ended_at' => null === $membership->endedAt ? null : Timestamps::toDatabase($membership->endedAt),
            'version' => $membership->version,
        ], ['version' => ParameterType::INTEGER]));
    }

    public function saveMembership(Membership $membership): void
    {
        $this->saved($this->guard(fn (): int|string => $this->connection->update('community_memberships', [
            'ended_at' => null === $membership->endedAt ? null : Timestamps::toDatabase($membership->endedAt),
            'version' => $membership->version,
        ], ['id' => $membership->id, 'version' => $membership->version - 1], ['version' => ParameterType::INTEGER])));
    }

    public function findLeadershipUnlocked(string $id): ?Leadership
    {
        $row = $this->select('community_leadership')->where('id = :id')->setParameter('id', $id)->fetchAssociative();

        return false === $row ? null : self::leadership($row);
    }

    public function activeLeadership(string $membershipId): ?Leadership
    {
        $row = $this->select('community_leadership')
            ->where('membership_id = :membership', 'ended_at IS NULL')
            ->setParameter('membership', $membershipId)
            ->fetchAssociative();

        return false === $row ? null : self::leadership($row);
    }

    public function insertLeadership(Leadership $leadership): void
    {
        $this->guard(fn (): int|string => $this->connection->insert('community_leadership', [
            'id' => $leadership->id,
            'membership_id' => $leadership->membershipId,
            'community_id' => $leadership->communityId,
            'account_id' => $leadership->accountId,
            'role' => $leadership->role,
            'started_at' => Timestamps::toDatabase($leadership->startedAt),
            'ended_at' => null === $leadership->endedAt ? null : Timestamps::toDatabase($leadership->endedAt),
            'version' => $leadership->version,
        ], ['version' => ParameterType::INTEGER]));
    }

    public function saveLeadership(Leadership $leadership): void
    {
        $this->saved($this->guard(fn (): int|string => $this->connection->update('community_leadership', [
            'ended_at' => null === $leadership->endedAt ? null : Timestamps::toDatabase($leadership->endedAt),
            'version' => $leadership->version,
        ], ['id' => $leadership->id, 'version' => $leadership->version - 1], ['version' => ParameterType::INTEGER])));
    }

    public function findFollowUpUnlocked(string $id): ?FollowUp
    {
        $row = $this->select('community_followups')->where('id = :id')->setParameter('id', $id)->fetchAssociative();

        return false === $row ? null : FollowUp::reconstitute(
            (string) $row['id'],
            (string) $row['community_id'],
            (string) $row['person_id'],
            (string) $row['note'],
            CalendarDate::parse((string) $row['due_on']),
            (string) $row['status'],
            (int) $row['version'],
            (string) $row['created_by'],
            Timestamps::fromDatabase((string) $row['created_at']),
        );
    }

    public function insertFollowUp(FollowUp $followUp): void
    {
        $this->guard(fn (): int|string => $this->connection->insert('community_followups', [
            'id' => $followUp->id,
            'community_id' => $followUp->communityId,
            'person_id' => $followUp->personId,
            'note' => $followUp->note,
            'due_on' => $followUp->dueOn->iso(),
            'status' => $followUp->status,
            'version' => $followUp->version,
            'created_by' => $followUp->createdBy,
            'created_at' => Timestamps::toDatabase($followUp->createdAt),
        ], ['version' => ParameterType::INTEGER]));
    }

    public function saveFollowUp(FollowUp $followUp): void
    {
        $this->saved($this->guard(fn (): int|string => $this->connection->update('community_followups', [
            'status' => $followUp->status,
            'version' => $followUp->version,
        ], ['id' => $followUp->id, 'version' => $followUp->version - 1], ['version' => ParameterType::INTEGER])));
    }

    private function select(string $table): QueryBuilder
    {
        return $this->connection->createQueryBuilder()->select('*')->from($table);
    }

    /**
     * @param callable(): (int|string) $write
     */
    private function guard(callable $write): int
    {
        try {
            return (int) $write();
        } catch (DriverException $error) {
            if (str_starts_with((string) $error->getSQLState(), '23')) {
                throw CommunityException::clash();
            }
            throw $error;
        }
    }

    private function saved(int $rows): void
    {
        if (1 !== $rows) {
            throw CommunityException::conflict('These details changed. Refresh and review before saving.');
        }
    }

    /** @param array<string, mixed> $row */
    private static function membership(array $row): Membership
    {
        return Membership::reconstitute(
            (string) $row['id'],
            (string) $row['community_id'],
            (string) $row['person_id'],
            Timestamps::fromDatabase((string) $row['started_at']),
            null === $row['ended_at'] ? null : Timestamps::fromDatabase((string) $row['ended_at']),
            (int) $row['version'],
        );
    }

    /** @param array<string, mixed> $row */
    private static function leadership(array $row): Leadership
    {
        return Leadership::reconstitute(
            (string) $row['id'],
            (string) $row['membership_id'],
            (string) $row['community_id'],
            null === $row['account_id'] ? null : (string) $row['account_id'],
            (string) $row['role'],
            Timestamps::fromDatabase((string) $row['started_at']),
            null === $row['ended_at'] ? null : Timestamps::fromDatabase((string) $row['ended_at']),
            (int) $row['version'],
        );
    }
}

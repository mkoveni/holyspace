<?php

declare(strict_types=1);

namespace App\Communities\Infrastructure\Persistence;

use App\Communities\Domain\Community;
use App\Communities\Domain\CommunityException;
use App\Communities\Domain\Leadership;
use App\Communities\Domain\Membership;
use App\Communities\Domain\Repository\CommunityRepository;
use DateTimeImmutable;
use Doctrine\DBAL\Connection;

final readonly class DbalCommunityRepository implements CommunityRepository
{
    public function __construct(private Connection $connection)
    {
    }

    public function lockCommunity(string $id): ?Community
    {
        $row = $this->connection->fetchAssociative('SELECT * FROM communities WHERE id = :id FOR UPDATE', ['id' => $id]);

        return $row === false ? null : Community::reconstitute(
            $row['id'],
            $row['kind'],
            $row['name'],
            $row['description'],
            $row['parent_id'],
            $row['campus'],
            (bool) $row['active'],
            (int) $row['version']
        );
    }

    public function insertCommunity(Community $community): void
    {
        $this->connection->insert('communities', $this->communityRow($community));
    }

    public function saveCommunity(Community $community, int $expectedVersion): void
    {
        $row = $this->communityRow($community);
        unset($row['id']);
        if ($this->connection->update('communities', $row, ['id' => $community->id, 'version' => $expectedVersion]) !== 1) {
            throw CommunityException::conflict('These details changed. Refresh and review before saving.');
        }
    }

    public function lockMembership(string $id): ?Membership
    {
        $row = $this->connection->fetchAssociative('SELECT * FROM community_memberships WHERE id = :id FOR UPDATE', ['id' => $id]);

        return $row === false ? null : new Membership(
            $row['id'],
            $row['community_id'],
            $row['person_id'],
            new DateTimeImmutable($row['started_at']),
            $row['ended_at'] === null ? null : new DateTimeImmutable($row['ended_at']),
            (int) $row['version']
        );
    }

    public function insertMembership(Membership $membership): void
    {
        $this->connection->insert('community_memberships', ['id' => $membership->id, 'community_id' => $membership->communityId,
            'person_id' => $membership->personId, 'started_at' => $membership->startedAt->format('Y-m-d H:i:s.uP'),
            'ended_at' => $membership->endedAt?->format('Y-m-d H:i:s.uP'), 'version' => $membership->version]);
    }

    public function saveMembership(Membership $membership, int $expectedVersion): void
    {
        if ($this->connection->update('community_memberships', ['ended_at' => $membership->endedAt?->format('Y-m-d H:i:s.uP'),
            'version' => $membership->version], ['id' => $membership->id, 'version' => $expectedVersion]) !== 1) {
            throw CommunityException::conflict('These details changed. Refresh and review before saving.');
        }
    }

    public function activeLeadership(string $membershipId): ?Leadership
    {
        $row = $this->connection->fetchAssociative('SELECT * FROM community_leadership WHERE membership_id = :id AND ended_at IS NULL FOR UPDATE', ['id' => $membershipId]);

        return $row === false ? null : new Leadership(
            $row['id'],
            $row['membership_id'],
            $row['community_id'],
            $row['account_id'],
            $row['role'],
            new DateTimeImmutable($row['started_at']),
            null,
            (int) $row['version']
        );
    }

    public function insertLeadership(Leadership $leadership): void
    {
        $this->connection->insert('community_leadership', ['id' => $leadership->id, 'membership_id' => $leadership->membershipId,
            'community_id' => $leadership->communityId, 'account_id' => $leadership->accountId, 'role' => $leadership->role,
            'started_at' => $leadership->startedAt->format('Y-m-d H:i:s.uP'), 'ended_at' => null, 'version' => $leadership->version]);
    }

    public function saveLeadership(Leadership $leadership, int $expectedVersion): void
    {
        if ($this->connection->update('community_leadership', ['ended_at' => $leadership->endedAt?->format('Y-m-d H:i:s.uP'),
            'version' => $leadership->version], ['id' => $leadership->id, 'version' => $expectedVersion]) !== 1) {
            throw CommunityException::conflict('These details changed. Refresh and review before saving.');
        }
    }

    /** @return array<string, mixed> */
    private function communityRow(Community $community): array
    {
        return ['id' => $community->id, 'kind' => $community->kind, 'name' => $community->name,
            'description' => $community->description, 'parent_id' => $community->parentId, 'campus' => $community->campus,
            'active' => $community->active, 'version' => $community->version];
    }
}

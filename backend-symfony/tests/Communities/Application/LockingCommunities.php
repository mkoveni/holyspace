<?php

declare(strict_types=1);

namespace App\Tests\Communities\Application;

use App\Communities\Domain\Community;
use App\Communities\Domain\FollowUp;
use App\Communities\Domain\Leadership;
use App\Communities\Domain\Membership;
use App\Communities\Domain\Repository\CommunityRepository;
use App\Tests\Events\Application\LockLog;
use DateTimeImmutable;
use LogicException;

/** In-memory communities that record when a community row is locked; only what meeting commands use is supported. */
final class LockingCommunities implements CommunityRepository
{
    /** @var array<string, Community> */
    public array $communities = [];

    /** @var list<Membership> */
    public array $memberships = [];

    public function __construct(private readonly LockLog $log)
    {
    }

    public function lockCommunity(string $id): ?Community
    {
        $this->log->locks[] = 'community:'.$id;

        return $this->communities[$id] ?? null;
    }

    public function insertCommunity(Community $community): void
    {
        $this->communities[$community->id] = $community;
    }

    public function saveCommunity(Community $community): void
    {
        $this->communities[$community->id] = $community;
    }

    public function findMembershipUnlocked(string $id): ?Membership
    {
        throw new LogicException('not used');
    }

    public function currentMembership(string $communityId, string $personId, DateTimeImmutable $at): ?Membership
    {
        throw new LogicException('not used');
    }

    public function membershipsAt(string $communityId, DateTimeImmutable $at, int $limit): array
    {
        return \array_slice(array_values(array_filter($this->memberships, static fn (Membership $m): bool => $m->communityId === $communityId && $m->covers($at))), 0, $limit);
    }

    public function insertMembership(Membership $membership): void
    {
        $this->memberships[] = $membership;
    }

    public function saveMembership(Membership $membership): void
    {
        throw new LogicException('not used');
    }

    public function findLeadershipUnlocked(string $id): ?Leadership
    {
        throw new LogicException('not used');
    }

    public function activeLeadership(string $membershipId): ?Leadership
    {
        throw new LogicException('not used');
    }

    public function insertLeadership(Leadership $leadership): void
    {
        throw new LogicException('not used');
    }

    public function saveLeadership(Leadership $leadership): void
    {
        throw new LogicException('not used');
    }

    public function findFollowUpUnlocked(string $id): ?FollowUp
    {
        throw new LogicException('not used');
    }

    public function insertFollowUp(FollowUp $followUp): void
    {
        throw new LogicException('not used');
    }

    public function saveFollowUp(FollowUp $followUp): void
    {
        throw new LogicException('not used');
    }
}

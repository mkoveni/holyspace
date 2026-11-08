<?php

declare(strict_types=1);

namespace App\Communities\Domain\Repository;

use App\Communities\Domain\Community;
use App\Communities\Domain\FollowUp;
use App\Communities\Domain\Leadership;
use App\Communities\Domain\Membership;
use DateTimeImmutable;

/**
 * Aggregates for commands. Lock order: a person (through People) before the community; the community row serializes
 * every membership and leadership change inside it. `find*Unlocked` loads are taken before those locks to learn which
 * rows to lock, and are reloaded after them.
 */
interface CommunityRepository
{
    public function lockCommunity(string $id): ?Community;

    public function insertCommunity(Community $community): void;

    public function saveCommunity(Community $community): void;

    /** @phpstan-impure handlers reload it after taking their locks */
    public function findMembershipUnlocked(string $id): ?Membership;

    public function currentMembership(string $communityId, string $personId, DateTimeImmutable $at): ?Membership;

    /**
     * Memberships covering the instant, by id; the caller asks for one more than it accepts to detect overflow.
     *
     * @return list<Membership>
     */
    public function membershipsAt(string $communityId, DateTimeImmutable $at, int $limit): array;

    public function insertMembership(Membership $membership): void;

    public function saveMembership(Membership $membership): void;

    /** @phpstan-impure handlers reload it after taking their locks */
    public function findLeadershipUnlocked(string $id): ?Leadership;

    public function activeLeadership(string $membershipId): ?Leadership;

    public function insertLeadership(Leadership $leadership): void;

    public function saveLeadership(Leadership $leadership): void;

    /** @phpstan-impure handlers reload it after taking their locks */
    public function findFollowUpUnlocked(string $id): ?FollowUp;

    public function insertFollowUp(FollowUp $followUp): void;

    public function saveFollowUp(FollowUp $followUp): void;
}

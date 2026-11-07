<?php

declare(strict_types=1);

namespace App\Communities\Domain\Repository;

use App\Communities\Domain\Community;
use App\Communities\Domain\Leadership;
use App\Communities\Domain\Membership;

interface CommunityRepository
{
    public function lockCommunity(string $id): ?Community;

    public function saveCommunity(Community $community, int $expectedVersion): void;

    public function insertCommunity(Community $community): void;

    public function lockMembership(string $id): ?Membership;

    public function insertMembership(Membership $membership): void;

    public function saveMembership(Membership $membership, int $expectedVersion): void;

    public function activeLeadership(string $membershipId): ?Leadership;

    public function insertLeadership(Leadership $leadership): void;

    public function saveLeadership(Leadership $leadership, int $expectedVersion): void;
}

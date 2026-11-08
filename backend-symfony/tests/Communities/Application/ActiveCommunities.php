<?php

declare(strict_types=1);

namespace App\Tests\Communities\Application;

use App\Communities\Application\Contract\CommunityReader;
use App\Communities\Application\Contract\CommunityView;
use App\Communities\Application\Contract\MembershipView;
use DateTimeImmutable;

/** Every community is an active group the caller leads; lists are empty; memberships are the ones added. */
final class ActiveCommunities implements CommunityReader
{
    /** @var array<string, MembershipView> */
    public array $memberships = [];

    public function community(string $id): CommunityView
    {
        return new CommunityView($id, 'GROUP', 'Home', '', null, '', true, 0);
    }

    public function directory(?string $kind, string $search, bool $archived, ?string $scopedAccount, int $limit, int $offset, DateTimeImmutable $at): array
    {
        return [];
    }

    public function directoryCount(?string $kind, string $search, bool $archived, ?string $scopedAccount, DateTimeImmutable $at): int
    {
        return 0;
    }

    public function leads(string $communityId, string $accountId, DateTimeImmutable $at): bool
    {
        return true;
    }

    public function membership(string $id): ?MembershipView
    {
        return $this->memberships[$id] ?? null;
    }

    public function members(string $communityId, bool $ended, int $limit, int $offset): array
    {
        return [];
    }

    public function memberCount(string $communityId, bool $ended): int
    {
        return 0;
    }

    public function leadership(string $communityId, int $limit, int $offset): array
    {
        return [];
    }

    public function history(string $communityId, int $limit, int $offset): array
    {
        return [];
    }

    public function followUps(string $communityId, bool $completed, int $limit, int $offset): array
    {
        return [];
    }
}

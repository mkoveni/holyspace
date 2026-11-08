<?php

declare(strict_types=1);

namespace App\Communities\Application\Contract;

use DateTimeImmutable;

/** Read models for the staff directory. Names come from People's directory; "Unavailable person" when withheld. */
interface CommunityReader
{
    public function community(string $id): ?CommunityView;

    /**
     * @param string|null $kind          MINISTRY, GROUP or null for both
     * @param string|null $scopedAccount when set, only active communities this account currently leads
     *
     * @return list<CommunityView> by name, then id
     */
    public function directory(?string $kind, string $search, bool $archived, ?string $scopedAccount, int $limit, int $offset, DateTimeImmutable $at): array;

    public function directoryCount(?string $kind, string $search, bool $archived, ?string $scopedAccount, DateTimeImmutable $at): int;

    /** Whether the account currently leads the active community through a current membership. */
    public function leads(string $communityId, string $accountId, DateTimeImmutable $at): bool;

    public function membership(string $id): ?MembershipView;

    /** @return list<MemberView> newest first; ended periods only when asked */
    public function members(string $communityId, bool $ended, int $limit, int $offset): array;

    public function memberCount(string $communityId, bool $ended): int;

    /** @return list<LeadershipView> newest first, current and ended */
    public function leadership(string $communityId, int $limit, int $offset): array;

    /** @return list<HistoryEntry> newest first */
    public function history(string $communityId, int $limit, int $offset): array;

    /** @return list<FollowUpView> by due date, then id; only OPEN unless completed ones are asked for */
    public function followUps(string $communityId, bool $completed, int $limit, int $offset): array;
}

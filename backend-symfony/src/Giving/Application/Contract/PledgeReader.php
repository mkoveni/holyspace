<?php

declare(strict_types=1);

namespace App\Giving\Application\Contract;

/** Read side of pledges (Java `PledgeReader`). Progress is live: cancelled assignments and reversed gifts never count. */
interface PledgeReader
{
    /** Pledges ordered by end date then id, optionally of one donor and one status (already validated). */
    public function list(?string $donorId, ?string $status, int $limit, int $offset): PledgePage;

    /** The pledge, a page of its assignments (newest first) and its latest 100 history entries; null when unknown. */
    public function detail(string $id, int $limit, int $offset): ?PledgeDetail;

    /**
     * The donor's unreversed gifts in the pledge currency and period with an unassigned amount on the pledge fund,
     * oldest first; empty for a cancelled pledge, null for an unknown one.
     *
     * @return list<PledgeCandidate>|null
     */
    public function candidates(string $id, int $limit, int $offset): ?array;
}

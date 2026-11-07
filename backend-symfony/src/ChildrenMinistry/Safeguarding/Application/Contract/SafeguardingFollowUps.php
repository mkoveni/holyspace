<?php

declare(strict_types=1);

namespace App\ChildrenMinistry\Safeguarding\Application\Contract;

/** The safeguarding worklist, published for the ministry overview. Dates and counts only: no evidence or care payload. */
interface SafeguardingFollowUps
{
    /**
     * @param string $kind  ALL, GUARDIAN, CONSENT, PICKUP or CARE
     * @param string $state ALL, MISSING, EXPIRED, EXPIRING or DUE
     *
     * @throws \App\ChildrenMinistry\Shared\Domain\ChildrenException when the cohort is larger than 1,000 child/programme records
     */
    public function list(?string $programmeId, string $search, string $kind, string $state, int $days, int $limit, int $offset, bool $includeCare): FollowUpView;
}

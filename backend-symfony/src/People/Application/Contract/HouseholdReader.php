<?php

declare(strict_types=1);

namespace App\People\Application\Contract;

use App\People\Application\HouseholdSummary;
use App\People\Application\HouseholdView;
use App\People\Application\MembershipView;

interface HouseholdReader
{
    public function find(string $id): ?HouseholdView;

    /** @return list<HouseholdSummary> */
    public function search(string $search, int $limit, int $offset): array;

    /** @return list<MembershipView> every membership of the person, oldest first */
    public function memberships(string $personId): array;
}

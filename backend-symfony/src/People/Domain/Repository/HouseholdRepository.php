<?php

declare(strict_types=1);

namespace App\People\Domain\Repository;

use App\People\Domain\Household;
use App\People\Domain\HouseholdMembership;

interface HouseholdRepository
{
    public function findForUpdate(string $id): ?Household;

    public function member(string $householdId, string $membershipId): ?HouseholdMembership;

    public function insert(Household $household): void;

    /** Saves a new revision. Throws a conflict when the stored version is not the previous one. */
    public function update(Household $household): void;

    public function add(HouseholdMembership $membership): void;

    public function end(HouseholdMembership $membership): void;
}

<?php

declare(strict_types=1);

namespace App\Giving\Domain\Repository;

use App\Giving\Domain\Pledge;
use App\Giving\Domain\PledgeAssignment;

/** Pledges and their assignments. Integrity violations (including the assignment guard trigger) become an evidence conflict. */
interface PledgeRepository
{
    /**
     * The pledge id when a create request with this id was already stored with the same hash, null when none was.
     * Throws a conflict when the id was used for different details.
     */
    public function pledgedBy(string $requestId, string $hash): ?string;

    public function insert(Pledge $pledge, string $hash): void;

    /** Locks the pledge row until the transaction ends. */
    public function findForUpdate(string $id): ?Pledge;

    /** Saves the next version. Throws a conflict when the stored version is not the previous one. */
    public function save(Pledge $pledge): void;

    /** Like pledgedBy, for an assignment request id. */
    public function assignedBy(string $requestId, string $hash): ?string;

    /**
     * Not locked: callers lock the gift and then the pledge, which serialise every write to its assignments, and read it again.
     *
     * @phpstan-impure
     */
    public function findAssignment(string $id): ?PledgeAssignment;

    public function insertAssignment(PledgeAssignment $assignment, string $hash): void;

    /** Saves the next version. Throws a conflict when the stored version is not the previous one. */
    public function saveAssignment(PledgeAssignment $assignment): void;

    /** Active assignments to the pledge, excluding reversed gifts. */
    public function credited(string $pledgeId): int;

    /** Active assignments of the gift's allocation to the fund, across all pledges. */
    public function assigned(string $giftId, string $fundId): int;
}

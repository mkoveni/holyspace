<?php

declare(strict_types=1);

namespace App\Giving\Infrastructure\Persistence;

use App\Giving\Domain\PledgeAssignment;
use App\Shared\Infrastructure\Persistence\Timestamps;

/** Maps a `giving_pledge_assignments` row (all columns) to the aggregate; shared by the pledge and online-giving stores. */
final class PledgeAssignmentRow
{
    private function __construct()
    {
    }

    /** @param array<string, mixed> $row */
    public static function toAssignment(array $row): PledgeAssignment
    {
        return PledgeAssignment::reconstitute(
            (string) $row['id'],
            (string) $row['pledge_id'],
            (string) $row['gift_id'],
            (string) $row['fund_id'],
            (string) $row['donor_id'],
            (string) $row['currency'],
            (int) $row['amount_minor'],
            (string) $row['status'],
            (int) $row['version'],
            null === $row['cancellation_reason'] ? null : (string) $row['cancellation_reason'],
            null === $row['cancelled_by'] ? null : (string) $row['cancelled_by'],
            null === $row['cancelled_at'] ? null : Timestamps::fromDatabase((string) $row['cancelled_at']),
            (string) $row['created_by'],
            Timestamps::fromDatabase((string) $row['created_at']),
        );
    }
}

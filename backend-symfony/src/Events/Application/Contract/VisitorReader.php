<?php

declare(strict_types=1);

namespace App\Events\Application\Contract;

interface VisitorReader
{
    /** @return list<VisitorRow> newest first */
    public function forOccurrence(string $occurrenceId, ?string $reviewStatus, int $limit, int $offset): array;

    public function count(string $occurrenceId, ?string $reviewStatus): int;

    public function find(string $registrationId): ?VisitorRow;

    /** The person the visitor's booking belongs to. */
    public function personId(string $registrationId): ?string;
}

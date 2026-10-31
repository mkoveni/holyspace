<?php

declare(strict_types=1);

namespace App\ChildrenMinistry\Application\ReadModel;

interface ChildAttendanceDirectory
{
    public function all(): array;

    public function forOccurrence(string $occurrenceId): array;
}

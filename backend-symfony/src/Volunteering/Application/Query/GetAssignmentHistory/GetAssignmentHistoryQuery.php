<?php

declare(strict_types=1);

namespace App\Volunteering\Application\Query\GetAssignmentHistory;

final readonly class GetAssignmentHistoryQuery
{
    public function __construct(
        public string $assignmentId,
        public int $limit,
        public int $offset,
    ) {
    }
}

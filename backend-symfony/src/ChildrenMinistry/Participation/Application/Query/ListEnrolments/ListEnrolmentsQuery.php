<?php

declare(strict_types=1);

namespace App\ChildrenMinistry\Participation\Application\Query\ListEnrolments;

final readonly class ListEnrolmentsQuery
{
    public function __construct(
        public string $childId,
        public int $limit,
        public int $offset,
    ) {
    }
}

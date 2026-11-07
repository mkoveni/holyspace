<?php

declare(strict_types=1);

namespace App\ChildrenMinistry\Participation\Application\Query\ListProgrammeChildren;

final readonly class ListProgrammeChildrenQuery
{
    public function __construct(
        public string $programmeId,
        public string $search,
        public ?string $status,
        public int $limit,
        public int $offset,
    ) {
    }
}

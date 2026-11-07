<?php

declare(strict_types=1);

namespace App\ChildrenMinistry\Participation\Application\Query\ListProgrammes;

final readonly class ListProgrammesQuery
{
    public function __construct(
        public string $search,
        public int $limit,
        public int $offset,
    ) {
    }
}

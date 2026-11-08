<?php

declare(strict_types=1);

namespace App\Volunteering\Application\Query\ListVolunteers;

final readonly class ListVolunteersQuery
{
    public function __construct(
        public ?string $search,
        public int $limit,
        public int $offset,
    ) {
    }
}

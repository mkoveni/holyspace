<?php

declare(strict_types=1);

namespace App\Volunteering\Application\Query\GetMyVolunteering;

final readonly class GetMyVolunteeringQuery
{
    public function __construct(
        public int $limit,
        public int $offset,
    ) {
    }
}

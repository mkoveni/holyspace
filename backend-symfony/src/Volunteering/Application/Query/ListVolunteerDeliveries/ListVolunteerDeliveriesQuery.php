<?php

declare(strict_types=1);

namespace App\Volunteering\Application\Query\ListVolunteerDeliveries;

final readonly class ListVolunteerDeliveriesQuery
{
    public function __construct(
        public ?string $status,
        public int $limit,
        public int $offset,
    ) {
    }
}

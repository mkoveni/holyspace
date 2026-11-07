<?php

declare(strict_types=1);

namespace App\ChildrenMinistry\Participation\Application\Query\ListRooms;

final readonly class ListRoomsQuery
{
    public function __construct(
        public ?string $locationId,
    ) {
    }
}

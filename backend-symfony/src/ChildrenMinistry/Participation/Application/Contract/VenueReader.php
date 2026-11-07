<?php

declare(strict_types=1);

namespace App\ChildrenMinistry\Participation\Application\Contract;

use App\ChildrenMinistry\Participation\Application\LocationView;
use App\ChildrenMinistry\Participation\Application\RoomView;

/** Read side of locations and rooms, published to the other Children Ministry modules. */
interface VenueReader
{
    public function location(string $id): ?LocationView;

    /** @return list<LocationView> ordered by name */
    public function locations(int $limit, int $offset): array;

    public function room(string $id): ?RoomView;

    /** @return list<RoomView> the location's rooms ordered by name */
    public function rooms(string $locationId): array;
}

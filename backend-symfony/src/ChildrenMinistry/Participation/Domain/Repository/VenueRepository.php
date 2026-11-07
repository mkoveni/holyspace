<?php

declare(strict_types=1);

namespace App\ChildrenMinistry\Participation\Domain\Repository;

use App\ChildrenMinistry\Participation\Domain\Location;
use App\ChildrenMinistry\Participation\Domain\Room;

/** Locations and the rooms inside them. */
interface VenueRepository
{
    public function insertLocation(Location $location): void;

    public function insertRoom(Room $room): void;
}

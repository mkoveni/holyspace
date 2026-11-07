<?php

declare(strict_types=1);

namespace App\ChildrenMinistry\Participation\Infrastructure\Persistence;

use App\ChildrenMinistry\Participation\Application\Contract\VenueReader;
use App\ChildrenMinistry\Participation\Application\LocationView;
use App\ChildrenMinistry\Participation\Application\RoomView;
use App\ChildrenMinistry\Participation\Domain\Location;
use App\ChildrenMinistry\Participation\Domain\Repository\VenueRepository;
use App\ChildrenMinistry\Participation\Domain\Room;
use Doctrine\DBAL\Connection;

final readonly class DbalVenueStore implements VenueRepository, VenueReader
{
    public function __construct(private Connection $connection)
    {
    }

    public function insertLocation(Location $location): void
    {
        $this->connection->insert('children_locations', ['id' => $location->id, 'name' => $location->name, 'time_zone' => $location->timeZone]);
    }

    public function insertRoom(Room $room): void
    {
        $this->connection->insert('children_rooms', [
            'id' => $room->id,
            'location_id' => $room->locationId,
            'name' => $room->name,
            'capacity' => $room->capacity,
        ]);
    }

    public function location(string $id): ?LocationView
    {
        $row = $this->connection->createQueryBuilder()->select('*')->from('children_locations')->where('id = :id')->setParameter('id', $id)->fetchAssociative();

        return false === $row ? null : self::locationView($row);
    }

    public function locations(int $limit, int $offset): array
    {
        $rows = $this->connection->createQueryBuilder()
            ->select('*')
            ->from('children_locations')
            ->orderBy('name')
            ->addOrderBy('id')
            ->setMaxResults($limit)
            ->setFirstResult($offset)
            ->fetchAllAssociative();

        return array_values(array_map(self::locationView(...), $rows));
    }

    public function room(string $id): ?RoomView
    {
        $row = $this->connection->createQueryBuilder()->select('*')->from('children_rooms')->where('id = :id')->setParameter('id', $id)->fetchAssociative();

        return false === $row ? null : self::roomView($row);
    }

    public function rooms(string $locationId): array
    {
        $rows = $this->connection->createQueryBuilder()
            ->select('*')
            ->from('children_rooms')
            ->where('location_id = :location')
            ->setParameter('location', $locationId)
            ->orderBy('name')
            ->addOrderBy('id')
            ->fetchAllAssociative();

        return array_values(array_map(self::roomView(...), $rows));
    }

    /** @param array<string, mixed> $row */
    private static function locationView(array $row): LocationView
    {
        return new LocationView((string) $row['id'], (string) $row['name'], (string) $row['time_zone']);
    }

    /** @param array<string, mixed> $row */
    private static function roomView(array $row): RoomView
    {
        return new RoomView((string) $row['id'], (string) $row['location_id'], (string) $row['name'], (int) $row['capacity']);
    }
}

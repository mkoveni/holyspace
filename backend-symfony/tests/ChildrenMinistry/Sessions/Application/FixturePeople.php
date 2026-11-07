<?php

declare(strict_types=1);

namespace App\Tests\ChildrenMinistry\Sessions\Application;

use App\People\Application\Contract\PersonDirectory;
use App\People\Application\Contract\PersonDirectoryEntry;
use App\Shared\Domain\CalendarDate;

/** The people a session fixture knows. A name starting with "Gone" marks an archived person. */
final readonly class FixturePeople implements PersonDirectory
{
    public function __construct(private SessionFixture $world)
    {
    }

    public function getAll(array $ids): array
    {
        return [];
    }

    public function matchingIds(string $search): array
    {
        return [];
    }

    public function get(string $id): ?PersonDirectoryEntry
    {
        $person = $this->world->people[$id] ?? null;

        return null === $person ? null : new PersonDirectoryEntry($id, $person[0], null === $person[1] ? null : CalendarDate::parse($person[1]), !str_starts_with($person[0], 'Gone'));
    }

    public function lock(string $id): ?PersonDirectoryEntry
    {
        $this->world->log->entries[] = 'person:'.$id;

        return $this->get($id);
    }
}

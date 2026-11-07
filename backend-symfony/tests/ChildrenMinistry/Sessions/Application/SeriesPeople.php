<?php

declare(strict_types=1);

namespace App\Tests\ChildrenMinistry\Sessions\Application;

use App\People\Application\Contract\PersonDirectory;
use App\People\Application\Contract\PersonDirectoryEntry;

/** The fixture's people, with the bulk lookup the series preview uses. */
final readonly class SeriesPeople implements PersonDirectory
{
    private FixturePeople $people;

    public function __construct(SessionFixture $world)
    {
        $this->people = new FixturePeople($world);
    }

    public function getAll(array $ids): array
    {
        $entries = [];
        foreach ($ids as $id) {
            $entry = $this->people->get($id);
            if (null !== $entry) {
                $entries[] = $entry;
            }
        }

        return $entries;
    }

    public function matchingIds(string $search): array
    {
        return [];
    }

    public function get(string $id): ?PersonDirectoryEntry
    {
        return $this->people->get($id);
    }

    public function lock(string $id): ?PersonDirectoryEntry
    {
        return $this->people->lock($id);
    }
}

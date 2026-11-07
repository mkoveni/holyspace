<?php

declare(strict_types=1);

namespace App\Tests\Events\Application;

use App\People\Application\Contract\PersonDirectory;
use App\People\Application\Contract\PersonDirectoryEntry;

/** In-memory test double: knows people by id and records the order they were locked in. */
final class RecordingPeople implements PersonDirectory
{
    /** @var array<string, PersonDirectoryEntry> */
    public array $people = [];

    public function __construct(private readonly LockLog $log = new LockLog())
    {
    }

    public function add(string $id, bool $available = true): void
    {
        $this->people[$id] = new PersonDirectoryEntry($id, 'Person '.substr($id, -2), null, $available);
    }

    public function getAll(array $ids): array
    {
        return array_values(array_intersect_key($this->people, array_flip($ids)));
    }

    public function matchingIds(string $search): array
    {
        return [];
    }

    public function get(string $id): ?PersonDirectoryEntry
    {
        return $this->people[$id] ?? null;
    }

    public function lock(string $id): ?PersonDirectoryEntry
    {
        $this->log->locks[] = 'person:'.$id;

        return $this->people[$id] ?? null;
    }
}

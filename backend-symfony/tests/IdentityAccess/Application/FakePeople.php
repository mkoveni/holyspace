<?php

declare(strict_types=1);

namespace App\Tests\IdentityAccess\Application;

use App\People\Application\Contract\PersonDirectory;
use App\People\Application\Contract\PersonDirectoryEntry;

final class FakePeople implements PersonDirectory
{
    /** @param array<string, bool> $availability person id => available */
    public function __construct(private readonly LockLog $log, private readonly array $availability)
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
        return $this->entry($id);
    }

    public function lock(string $id): ?PersonDirectoryEntry
    {
        $this->log->entries[] = 'person';

        return $this->entry($id);
    }

    private function entry(string $id): ?PersonDirectoryEntry
    {
        return isset($this->availability[$id]) ? new PersonDirectoryEntry($id, 'Person '.$id, null, $this->availability[$id]) : null;
    }
}

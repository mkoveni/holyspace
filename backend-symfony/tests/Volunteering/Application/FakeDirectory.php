<?php

declare(strict_types=1);

namespace App\Tests\Volunteering\Application;

use App\People\Application\Contract\PersonDirectory;
use App\People\Application\Contract\PersonDirectoryEntry;

final class FakeDirectory implements PersonDirectory
{
    /** @param array<string, bool> $availability person id => available */
    public function __construct(private readonly array $availability)
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
        return isset($this->availability[$id]) ? new PersonDirectoryEntry($id, 'Person '.$id, null, $this->availability[$id]) : null;
    }

    public function lock(string $id): ?PersonDirectoryEntry
    {
        return $this->get($id);
    }
}

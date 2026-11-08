<?php

declare(strict_types=1);

namespace App\Tests\Volunteering\Application\Teams;

use App\People\Application\Contract\PersonDirectory;
use App\People\Application\Contract\PersonDirectoryEntry;

final readonly class JournalPeople implements PersonDirectory
{
    public function __construct(private LockJournal $journal)
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

    public function get(string $id): PersonDirectoryEntry
    {
        return new PersonDirectoryEntry($id, 'Person '.$id, null, true);
    }

    public function lock(string $id): PersonDirectoryEntry
    {
        $this->journal->entries[] = 'person:'.$id;

        return $this->get($id);
    }
}

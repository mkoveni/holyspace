<?php

declare(strict_types=1);

namespace App\ChildrenMinistry\Shared\Application;

use App\ChildrenMinistry\Shared\Application\Contract\ChildLocks;
use App\ChildrenMinistry\Shared\Domain\ChildrenException;
use App\People\Application\Contract\PersonDirectory;
use App\People\Application\Contract\PersonDirectoryEntry;

/** How Children Ministry reaches people: lookups that insist the person exists and is available, and child locking. */
final readonly class ParticipantAccess
{
    public function __construct(private PersonDirectory $people, private ChildLocks $locks)
    {
    }

    public function person(string $id): PersonDirectoryEntry
    {
        return $this->people->get($id) ?? throw ChildrenException::missing('Person');
    }

    public function available(string $id): PersonDirectoryEntry
    {
        $person = $this->person($id);
        if (!$person->available) {
            throw ChildrenException::conflict('Restore the person or review their life status first.');
        }

        return $person;
    }

    /**
     * Locks the people involved in id order (preventing deadlocks), then the child's guard row.
     * Callers take this before any session lock.
     */
    public function lockChild(string $childId, string ...$others): void
    {
        $ids = array_values(array_unique([$childId, ...$others]));
        sort($ids, SORT_STRING);
        foreach ($ids as $id) {
            $this->people->lock($id) ?? throw ChildrenException::missing('Person');
        }
        $this->locks->lock($childId);
    }
}

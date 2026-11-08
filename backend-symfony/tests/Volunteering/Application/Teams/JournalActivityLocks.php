<?php

declare(strict_types=1);

namespace App\Tests\Volunteering\Application\Teams;

use App\Volunteering\Application\Contract\ServingActivityLocks;

final readonly class JournalActivityLocks implements ServingActivityLocks
{
    public function __construct(private LockJournal $journal, private string $name)
    {
    }

    public function lock(array $activities): void
    {
        foreach ($activities as $activity) {
            $this->journal->entries[] = $this->name.':'.$activity->kind.':'.$activity->id;
        }
    }
}

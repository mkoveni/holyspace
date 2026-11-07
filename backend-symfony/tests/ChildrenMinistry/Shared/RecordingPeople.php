<?php

declare(strict_types=1);

namespace App\Tests\ChildrenMinistry\Shared;

use App\People\Application\Contract\PersonDirectory;
use App\People\Application\Contract\PersonDirectoryEntry;
use App\Shared\Domain\CalendarDate;

/** A person directory that knows a fixed set of people and logs every lock. */
final readonly class RecordingPeople implements PersonDirectory
{
    /**
     * @param array<string, bool>   $people     person id => available
     * @param array<string, string> $birthDates person id => YYYY-MM-DD
     */
    public function __construct(private CallLog $log, private array $people, private array $birthDates = [])
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
        return isset($this->people[$id]) ? new PersonDirectoryEntry($id, 'P', isset($this->birthDates[$id]) ? CalendarDate::parse($this->birthDates[$id]) : null, $this->people[$id]) : null;
    }

    public function lock(string $id): ?PersonDirectoryEntry
    {
        $this->log->entries[] = 'person:'.$id;

        return $this->get($id);
    }
}

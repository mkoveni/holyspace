<?php

declare(strict_types=1);

namespace App\Tests\Events\Application;

use App\Events\Application\Contract\VenuePage;
use App\Events\Application\Contract\VenueReader;
use App\Events\Application\Contract\VenueView;
use App\Events\Domain\Repository\VenueRepository;
use App\Events\Domain\Venue;

/** In-memory test double: no database. */
final class InMemoryVenueRepository implements VenueRepository, VenueReader
{
    /** @var array<string, Venue> */
    public array $venues = [];

    public function __construct(private readonly LockLog $log = new LockLog())
    {
    }

    public function findForUpdate(string $id): ?Venue
    {
        $this->log->locks[] = 'venue:'.$id;

        return $this->venues[$id] ?? null;
    }

    public function findUnlocked(string $id): ?Venue
    {
        return $this->venues[$id] ?? null;
    }

    public function insert(Venue $venue): void
    {
        $this->venues[$venue->id] = $venue;
    }

    public function save(Venue $venue): void
    {
        $this->venues[$venue->id] = $venue;
    }

    public function find(string $id): ?VenueView
    {
        $venue = $this->venues[$id] ?? null;

        return null === $venue ? null : new VenueView($venue->id, $venue->name, $venue->address, $venue->exclusive, $venue->active, $venue->version);
    }

    public function search(string $search, bool $includeArchived, int $limit, int $offset): VenuePage
    {
        return new VenuePage([], 0);
    }
}

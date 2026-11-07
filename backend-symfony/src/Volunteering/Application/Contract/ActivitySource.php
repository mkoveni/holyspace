<?php

declare(strict_types=1);

namespace App\Volunteering\Application\Contract;

use DateTimeImmutable;

/** Implemented by contexts whose activities can be staffed with volunteers (Events, Children Ministry sessions). */
interface ActivitySource
{
    /** Stable discriminator stored on volunteer shifts, for example CHILDREN_SESSION. */
    public function kind(): string;

    public function find(string $id): ?SourceActivity;

    /**
     * Activities starting in the window, ordered by start. A null limit returns all of them.
     *
     * @return list<SourceActivity>
     */
    public function upcoming(
        DateTimeImmutable $from,
        DateTimeImmutable $until,
        ?int $limit = null,
        int $offset = 0,
    ): array;
}

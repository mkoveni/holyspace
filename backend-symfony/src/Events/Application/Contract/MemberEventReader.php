<?php

declare(strict_types=1);

namespace App\Events\Application\Contract;

use DateTimeImmutable;

interface MemberEventReader
{
    public function offer(string $eventId): MemberOffer;

    /**
     * Upcoming occurrences overlapping the window: the published catalogue, or only those the person has a booking on.
     *
     * @return list<MemberEventItem>
     */
    public function list(string $personId, DateTimeImmutable $from, DateTimeImmutable $until, bool $mine, int $limit, int $offset): array;

    /** A published occurrence, or one the person has a booking on, even if since withdrawn. */
    public function detail(string $personId, string $occurrenceId): ?MemberEventItem;
}

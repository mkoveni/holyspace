<?php

declare(strict_types=1);

namespace App\Events\Domain;

use DateTimeImmutable;

/** The locked facts about an occurrence that decide whether it can sell tickets. */
final readonly class SaleOccurrence
{
    public function __construct(public string $id, public string $status, public DateTimeImmutable $endsAt, public ?int $capacity, public bool $eventActive)
    {
    }

    public function sellable(DateTimeImmutable $now): bool
    {
        return 'SCHEDULED' === $this->status && $this->eventActive && $this->endsAt > $now;
    }
}

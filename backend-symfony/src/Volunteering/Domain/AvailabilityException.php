<?php

declare(strict_types=1);

namespace App\Volunteering\Domain;

use DateTimeImmutable;

/** A one-off window in which the volunteer is explicitly available or unavailable. */
final readonly class AvailabilityException
{
    public function __construct(public DateTimeImmutable $startsAt, public DateTimeImmutable $endsAt, public bool $available)
    {
        ServingRules::period($startsAt, $endsAt);
    }
}

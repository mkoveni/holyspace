<?php

declare(strict_types=1);

namespace App\Volunteering\Application\Contract;

use App\Volunteering\Domain\AvailabilityException;

/** Java's `AvailabilityException` record: a stored one-off window of one availability revision. */
final readonly class AvailabilityExceptionRecord
{
    public function __construct(public string $id, public string $volunteerId, public int $revision, public AvailabilityException $window)
    {
    }
}

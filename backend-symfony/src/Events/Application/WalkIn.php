<?php

declare(strict_types=1);

namespace App\Events\Application;

/** The outcome of a walk-in: the booking made at the door and whether the person was admitted. */
final readonly class WalkIn
{
    public function __construct(public string $registrationId, public bool $admitted, public string $status)
    {
    }
}

<?php

declare(strict_types=1);

namespace App\Volunteering\Application;

use App\Volunteering\Domain\Assignment;
use App\Volunteering\Domain\Shift;
use App\Volunteering\Domain\Volunteer;

/** An assignment held with its volunteer and shift, locked in that order. */
final readonly class LockedAssignment
{
    public function __construct(public Volunteer $volunteer, public Shift $shift, public Assignment $assignment)
    {
    }
}

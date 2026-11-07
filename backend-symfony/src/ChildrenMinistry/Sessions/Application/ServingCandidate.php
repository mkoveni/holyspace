<?php

declare(strict_types=1);

namespace App\ChildrenMinistry\Sessions\Application;

use App\Volunteering\Application\Contract\StaffingAssessment;

/** A confirmed serving assignment for the session, and whether its person is a registered Children Ministry worker. */
final readonly class ServingCandidate
{
    public function __construct(public StaffingAssessment $assignment, public bool $workerRegistered)
    {
    }
}

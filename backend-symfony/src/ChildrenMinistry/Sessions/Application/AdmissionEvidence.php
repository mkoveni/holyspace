<?php

declare(strict_types=1);

namespace App\ChildrenMinistry\Sessions\Application;

/** The records that admitted a child to a session, kept on the attendance segment. */
final readonly class AdmissionEvidence
{
    public function __construct(public string $enrolmentId, public string $assignmentId, public string $consentId)
    {
    }
}

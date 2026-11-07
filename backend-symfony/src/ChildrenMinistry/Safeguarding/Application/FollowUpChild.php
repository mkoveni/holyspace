<?php

declare(strict_types=1);

namespace App\ChildrenMinistry\Safeguarding\Application;

use DateTimeImmutable;

/** An active enrolment and how long its guardian authority and current-policy consent last. */
final readonly class FollowUpChild
{
    public function __construct(public string $childId, public string $programmeId, public string $programme, public ?string $policyId, public ?DateTimeImmutable $guardianUntil, public ?DateTimeImmutable $consentUntil)
    {
    }
}

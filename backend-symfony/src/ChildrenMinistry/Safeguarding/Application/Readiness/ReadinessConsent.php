<?php

declare(strict_types=1);

namespace App\ChildrenMinistry\Safeguarding\Application\Readiness;

final readonly class ReadinessConsent
{
    public function __construct(public string $childId, public string $authorityId)
    {
    }
}

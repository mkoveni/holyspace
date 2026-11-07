<?php

declare(strict_types=1);

namespace App\ChildrenMinistry\Safeguarding\Application\Readiness;

final readonly class ReadinessGuardian
{
    public function __construct(public string $id, public string $childId)
    {
    }
}

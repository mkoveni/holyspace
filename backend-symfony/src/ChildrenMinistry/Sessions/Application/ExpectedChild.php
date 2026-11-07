<?php

declare(strict_types=1);

namespace App\ChildrenMinistry\Sessions\Application;

final readonly class ExpectedChild
{
    public function __construct(public string $childId, public string $name, public string $enrolmentId, public string $assignmentId)
    {
    }
}

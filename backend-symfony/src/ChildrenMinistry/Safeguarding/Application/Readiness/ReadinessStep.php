<?php

declare(strict_types=1);

namespace App\ChildrenMinistry\Safeguarding\Application\Readiness;

final readonly class ReadinessStep
{
    public function __construct(public string $code, public bool $complete)
    {
    }
}

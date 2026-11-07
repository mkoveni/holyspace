<?php

declare(strict_types=1);

namespace App\ChildrenMinistry\Safeguarding\Application\Contract;

/** One preparation step and whether it is complete. A code only, never the evidence behind it. */
final readonly class ReadinessStepStatus
{
    public function __construct(public string $code, public bool $complete)
    {
    }
}

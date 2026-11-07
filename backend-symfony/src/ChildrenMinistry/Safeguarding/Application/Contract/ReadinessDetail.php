<?php

declare(strict_types=1);

namespace App\ChildrenMinistry\Safeguarding\Application\Contract;

/** A child's programme preparation with its steps, for families. Completion flags only. */
final readonly class ReadinessDetail
{
    /** @param list<ReadinessStepStatus> $steps */
    public function __construct(public ReadinessSummary $summary, public array $steps, public string $timeZone)
    {
    }
}

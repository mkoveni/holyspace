<?php

declare(strict_types=1);

namespace App\ChildrenMinistry\Safeguarding\Application\Readiness;

use App\ChildrenMinistry\Safeguarding\Application\Contract\ReadinessDetail;
use App\ChildrenMinistry\Safeguarding\Application\Contract\ReadinessStepStatus;
use App\ChildrenMinistry\Safeguarding\Application\Contract\ReadinessSummary;
use App\Shared\Domain\CalendarDate;
use DateTimeImmutable;

/** A programme preparation snapshot for one child. Completion flags only, never care or guardian evidence. */
final readonly class ReadinessView
{
    /** @param list<ReadinessStep> $steps */
    public function __construct(
        public bool $ready,
        public int $completed,
        public int $total,
        public CalendarDate $on,
        public DateTimeImmutable $checkedAt,
        public array $steps,
        public ?ReadinessEnrolment $enrolment,
        public string $timeZone,
    ) {
    }

    public function summary(): ReadinessSummary
    {
        return new ReadinessSummary($this->ready, $this->completed, $this->total);
    }

    public function detail(): ReadinessDetail
    {
        return new ReadinessDetail($this->summary(), array_map(static fn (ReadinessStep $step): ReadinessStepStatus => new ReadinessStepStatus($step->code, $step->complete), $this->steps), $this->timeZone);
    }
}

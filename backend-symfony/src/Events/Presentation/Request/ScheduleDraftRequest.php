<?php

declare(strict_types=1);

namespace App\Events\Presentation\Request;

use App\Events\Application\ScheduleDraft;
use App\Events\Domain\TimeOfDay;
use App\Shared\Domain\CalendarDate;
use Symfony\Component\Validator\Constraints as Assert;

final readonly class ScheduleDraftRequest
{
    public function __construct(
        #[Assert\NotNull]
        #[Assert\Uuid]
        public ?string $venueId = null,
        #[Assert\NotNull]
        public ?string $timeZone = null,
        #[Assert\NotNull]
        public ?string $frequency = null,
        #[Assert\NotNull]
        public ?int $interval = null,
        #[Assert\NotNull]
        public ?int $count = null,
        #[Assert\NotNull]
        public ?string $firstDate = null,
        #[Assert\NotNull]
        public ?string $startsAt = null,
        #[Assert\NotNull]
        public ?string $endsAt = null,
        #[Assert\NotNull]
        public ?int $endDayOffset = null,
    ) {
    }

    public function draft(): ScheduleDraft
    {
        return new ScheduleDraft(
            strtolower((string) $this->venueId),
            (string) $this->timeZone,
            (string) $this->frequency,
            (int) $this->interval,
            (int) $this->count,
            CalendarDate::parse((string) $this->firstDate),
            TimeOfDay::parse($this->startsAt),
            TimeOfDay::parse($this->endsAt),
            (int) $this->endDayOffset,
        );
    }
}

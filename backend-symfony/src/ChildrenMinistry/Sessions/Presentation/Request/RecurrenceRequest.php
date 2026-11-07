<?php

declare(strict_types=1);

namespace App\ChildrenMinistry\Sessions\Presentation\Request;

use App\ChildrenMinistry\Sessions\Domain\WeeklyRecurrence;
use App\Shared\Domain\CalendarDate;
use Symfony\Component\Validator\Constraints as Assert;

final readonly class RecurrenceRequest
{
    /** @param list<string> $excludedDates */
    public function __construct(
        #[Assert\NotNull]
        public ?string $firstDate = null,
        #[Assert\NotNull]
        public ?string $startsAt = null,
        #[Assert\NotNull]
        public ?string $endsAt = null,
        public int $intervalWeeks = 0,
        public int $occurrences = 0,
        #[Assert\Count(max: 52), Assert\All([new Assert\Type('string')])]
        public array $excludedDates = [],
    ) {
    }

    public function toRecurrence(): WeeklyRecurrence
    {
        return new WeeklyRecurrence(
            CalendarDate::parse((string) $this->firstDate),
            (string) $this->startsAt,
            (string) $this->endsAt,
            $this->intervalWeeks,
            $this->occurrences,
            array_map(CalendarDate::parse(...), $this->excludedDates),
        );
    }
}

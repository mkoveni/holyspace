<?php

declare(strict_types=1);

namespace App\Communities\Presentation\Request;

use App\Communities\Application\MeetingCalendar;
use App\Events\Application\Contract\CommunitySchedule;
use Symfony\Component\Validator\Constraints as Assert;

/** Java `CommunityCalendar.Schedule`; every field is required, as the Events schedule body is in this port. */
final readonly class CommunityScheduleRequest
{
    public function __construct(
        #[Assert\NotNull, Assert\Uuid]
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

    public function schedule(): CommunitySchedule
    {
        return MeetingCalendar::schedule(
            (string) $this->venueId,
            (string) $this->timeZone,
            (string) $this->frequency,
            (int) $this->interval,
            (int) $this->count,
            (string) $this->firstDate,
            (string) $this->startsAt,
            (string) $this->endsAt,
            (int) $this->endDayOffset,
        );
    }
}

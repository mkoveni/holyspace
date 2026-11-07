<?php

declare(strict_types=1);

namespace App\Events\Presentation\Request;

use App\Events\Application\OccurrenceChangeDraft;
use App\Events\Domain\TimeOfDay;
use App\Shared\Domain\CalendarDate;
use Symfony\Component\Validator\Constraints as Assert;

final readonly class OccurrenceChangeDraftRequest
{
    public function __construct(
        #[Assert\NotNull]
        #[Assert\Uuid]
        public ?string $occurrenceId = null,
        #[Assert\NotNull]
        #[Assert\PositiveOrZero]
        public ?int $expectedVersion = null,
        public ?string $action = null,
        public ?string $scope = null,
        public ?string $date = null,
        public ?string $startsAt = null,
        public ?string $endsAt = null,
        public int $endDayOffset = 0,
        #[Assert\Uuid]
        public ?string $venueId = null,
    ) {
    }

    public function draft(): OccurrenceChangeDraft
    {
        return new OccurrenceChangeDraft(
            strtolower((string) $this->occurrenceId),
            (int) $this->expectedVersion,
            $this->action,
            $this->scope,
            null === $this->date ? null : CalendarDate::parse($this->date),
            null === $this->startsAt ? null : TimeOfDay::parse($this->startsAt),
            null === $this->endsAt ? null : TimeOfDay::parse($this->endsAt),
            $this->endDayOffset,
            null === $this->venueId ? null : strtolower($this->venueId),
        );
    }
}

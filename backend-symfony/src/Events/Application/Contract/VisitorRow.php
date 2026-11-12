<?php

declare(strict_types=1);

namespace App\Events\Application\Contract;

use DateTimeImmutable;

final readonly class VisitorRow
{
    public function __construct(
        public string $registrationId,
        public string $occurrenceId,
        public string $fullName,
        public ?string $mobile,
        public ?string $email,
        public ?string $howHeard,
        public ?int $partyAdults,
        public bool $firstTime,
        public string $reviewStatus,
        public DateTimeImmutable $checkedInAt,
        public string $attendanceStatus,
        public ?int $attendanceVersion,
        /** @var list<VisitorChildRow> */
        public array $children = [],
    ) {
    }
}

<?php

declare(strict_types=1);

namespace App\ChildrenMinistry\Sessions\Application;

use DateTimeImmutable;

final readonly class FamilySession
{
    /** @param list<string> $issues */
    public function __construct(
        public string $id,
        public string $programme,
        public string $className,
        public string $room,
        public string $location,
        public string $timeZone,
        public DateTimeImmutable $startsAt,
        public DateTimeImmutable $endsAt,
        public string $status,
        public array $issues,
        public bool $arrivalWindowOpen,
    ) {
    }
}

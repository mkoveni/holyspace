<?php

declare(strict_types=1);

namespace App\Events\Application\Contract;

use DateTimeImmutable;

/** What a visitor sees before they fill in the form: which gathering this is and whether check-in is open. */
final readonly class VisitInvitation
{
    public function __construct(
        public string $title,
        public string $venueName,
        public DateTimeImmutable $startsAt,
        public DateTimeImmutable $endsAt,
        public string $timeZone,
        public bool $open,
    ) {
    }
}

<?php

declare(strict_types=1);

namespace App\Events\Application;

use App\Events\Application\Contract\RegistrationCounts;
use App\Events\Domain\EventOccurrence;

/** One occurrence before and after a reviewed change, with what the change would affect. */
final readonly class OccurrenceChange
{
    public function __construct(
        public EventOccurrence $before,
        public EventOccurrence $after,
        public RegistrationCounts $registrations,
        public int $registrationVersion,
        public int $attended,
    ) {
    }
}

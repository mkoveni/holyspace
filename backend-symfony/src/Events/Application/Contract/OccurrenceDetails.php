<?php

declare(strict_types=1);

namespace App\Events\Application\Contract;

final readonly class OccurrenceDetails
{
    public function __construct(
        public OccurrenceView $occurrence,
        public string $title,
        public string $type,
        public SeriesView $series,
        public PolicyView $registrationPolicy,
        public RegistrationCounts $registrations,
    ) {
    }
}

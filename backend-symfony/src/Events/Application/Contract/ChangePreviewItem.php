<?php

declare(strict_types=1);

namespace App\Events\Application\Contract;

final readonly class ChangePreviewItem
{
    public function __construct(
        public OccurrenceView $before,
        public OccurrenceView $after,
        public RegistrationCounts $registrations,
        public int $registrationVersion,
        public int $attended,
    ) {
    }
}

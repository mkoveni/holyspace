<?php

declare(strict_types=1);

namespace App\Volunteering\Application\Query\ReviewVolunteerReminder;

final readonly class ReviewVolunteerReminderQuery
{
    public function __construct(
        public string $assignmentId,
        public string $contactId,
        public ?string $message,
    ) {
    }
}

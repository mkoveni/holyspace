<?php

declare(strict_types=1);

namespace App\Volunteering\Application\Command\ApproveVolunteerReminder;

use App\Shared\Application\Command;

/** @implements Command<string> */
final readonly class ApproveVolunteerReminderCommand implements Command
{
    public function __construct(
        public string $actorId,
        public ?string $requestId,
        public string $assignmentId,
        public string $contactId,
        public ?string $message,
        public ?string $reviewHash,
        public ?string $verificationEvidence,
    ) {
    }
}

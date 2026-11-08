<?php

declare(strict_types=1);

namespace App\Volunteering\Application\Contract;

/** Java `ReminderReview.Review`: the rendered reminder and the hash binding assignment, duty, contact and content. */
final readonly class ReminderPreview
{
    public function __construct(
        public string $assignmentId,
        public int $assignmentVersion,
        public int $shiftRevision,
        public string $contactId,
        public string $recipient,
        public string $subject,
        public string $html,
        public string $hash,
        public int $templateVersion,
    ) {
    }

    /** @return array<string, string> */
    public function __debugInfo(): array
    {
        return ['assignmentId' => $this->assignmentId];
    }
}

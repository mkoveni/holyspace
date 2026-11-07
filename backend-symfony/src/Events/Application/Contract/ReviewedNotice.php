<?php

declare(strict_types=1);

namespace App\Events\Application\Contract;

/** A rendered notice for one booking and one email address, with the hash that approval must present. */
final readonly class ReviewedNotice
{
    public function __construct(
        public string $registrationId,
        public int $registrationVersion,
        public int $scheduleRevision,
        public int $eventVersion,
        public string $purpose,
        public string $contactId,
        public string $recipient,
        public string $subject,
        public string $html,
        public string $hash,
        public int $templateVersion,
    ) {
    }

    /** The recipient and content stay out of logs. */
    public function __debugInfo(): array
    {
        return ['registrationId' => $this->registrationId];
    }
}

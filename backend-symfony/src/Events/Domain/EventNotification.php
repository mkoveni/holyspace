<?php

declare(strict_types=1);

namespace App\Events\Domain;

use DateTimeImmutable;

/** The immutable record of an approved notice: what was reviewed, by whom and which delivery carries it. */
final readonly class EventNotification
{
    public function __construct(
        public string $id,
        public string $registrationId,
        public int $registrationVersion,
        public int $scheduleRevision,
        public int $eventVersion,
        public string $purpose,
        public string $contactId,
        public string $reviewHash,
        public string $requestHash,
        public string $evidence,
        public string $createdBy,
        public DateTimeImmutable $createdAt,
        public string $deliveryId,
    ) {
    }

    /** Hides the recipient-bound evidence from logs and stack traces. */
    public function __debugInfo(): array
    {
        return ['id' => $this->id];
    }
}

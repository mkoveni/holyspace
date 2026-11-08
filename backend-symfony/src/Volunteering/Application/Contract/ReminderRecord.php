<?php

declare(strict_types=1);

namespace App\Volunteering\Application\Contract;

use DateTimeImmutable;

/** Java `ReminderReader.Reminder`. The evidence is the approver's recipient check and never leaves Volunteering. */
final readonly class ReminderRecord
{
    public function __construct(
        public string $id,
        public string $assignmentId,
        public int $assignmentVersion,
        public int $shiftVersion,
        public string $contactId,
        public string $reviewHash,
        public string $evidence,
        public string $createdBy,
        public DateTimeImmutable $createdAt,
        public string $deliveryId,
    ) {
    }

    /** @return array<string, string> */
    public function __debugInfo(): array
    {
        return ['id' => $this->id];
    }
}

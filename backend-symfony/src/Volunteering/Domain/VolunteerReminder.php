<?php

declare(strict_types=1);

namespace App\Volunteering\Domain;

use DateTimeImmutable;

/**
 * An approved reminder: the reviewed assignment and shift revisions, the contact, the review hash and the approver's
 * recipient-verification evidence, frozen with the delivery it queued. The request id is the reminder id.
 */
final readonly class VolunteerReminder
{
    private function __construct(
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

    public static function approve(string $id, string $assignmentId, int $assignmentVersion, int $shiftVersion, string $contactId, string $reviewHash, string $evidence, string $createdBy, DateTimeImmutable $createdAt, string $deliveryId): self
    {
        return new self($id, $assignmentId, $assignmentVersion, $shiftVersion, $contactId, $reviewHash, ServingRules::text($evidence, 500), $createdBy, $createdAt, $deliveryId);
    }

    /** @return array<string, string> */
    public function __debugInfo(): array
    {
        return ['id' => $this->id];
    }
}

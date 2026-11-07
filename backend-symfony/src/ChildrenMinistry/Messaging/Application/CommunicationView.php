<?php

declare(strict_types=1);

namespace App\ChildrenMinistry\Messaging\Application;

use App\ChildrenMinistry\Messaging\Domain\CommunicationStatus;
use App\ChildrenMinistry\Messaging\Domain\CommunicationType;
use App\Shared\Domain\CalendarDate;
use DateTimeImmutable;

/** A prepared guardian message, including its wording. */
final readonly class CommunicationView
{
    public function __construct(
        public string $id,
        public string $childId,
        public string $authorityId,
        public string $guardianId,
        public string $programmeId,
        public ?string $policyId,
        public ?string $sessionId,
        public CommunicationType $type,
        public string $message,
        public CalendarDate $dueOn,
        public CommunicationStatus $status,
        public string $createdBy,
        public DateTimeImmutable $createdAt,
        public string $updatedBy,
        public DateTimeImmutable $updatedAt,
        public string $contactReference,
        public int $version,
    ) {
    }

    public function __debugInfo(): array
    {
        return ['id' => $this->id, 'status' => $this->status->value];
    }
}

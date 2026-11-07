<?php

declare(strict_types=1);

namespace App\ChildrenMinistry\Messaging\Domain;

use App\ChildrenMinistry\Shared\Domain\ChildrenException;
use App\ChildrenMinistry\Shared\Domain\ChildrenRules;
use App\Shared\Domain\CalendarDate;
use DateTimeImmutable;

/** A prepared message to a child's guardian, and the record that someone made (or declined to make) contact. */
final readonly class GuardianCommunication
{
    public string $message;

    public string $contactReference;

    private function __construct(
        public string $id,
        public string $childId,
        public string $authorityId,
        public string $guardianId,
        public string $programmeId,
        public ?string $policyId,
        public ?string $sessionId,
        public CommunicationType $type,
        string $message,
        public CalendarDate $dueOn,
        public CommunicationStatus $status,
        public string $createdBy,
        public DateTimeImmutable $createdAt,
        public string $updatedBy,
        public DateTimeImmutable $updatedAt,
        string $contactReference,
        public int $version,
    ) {
        $this->message = ChildrenRules::text($message, 3000);
        $this->contactReference = ChildrenRules::optional($contactReference, 500);
        if ((CommunicationType::SessionReminder === $type) !== (null !== $sessionId)) {
            throw ChildrenException::invalid('Choose a communication type and the required session.');
        }
    }

    public static function prepare(string $id, string $childId, string $authorityId, string $guardianId, string $programmeId, ?string $policyId, ?string $sessionId, ?string $type, string $message, CalendarDate $dueOn, string $createdBy, DateTimeImmutable $now): self
    {
        $type = null === $type ? null : CommunicationType::tryFrom($type);
        if (null === $type) {
            throw ChildrenException::invalid('Choose a communication type and the required session.');
        }

        return new self($id, $childId, $authorityId, $guardianId, $programmeId, $policyId, $sessionId, $type, $message, $dueOn, CommunicationStatus::Draft, $createdBy, $now, $createdBy, $now, '', 0);
    }

    public static function reconstitute(string $id, string $childId, string $authorityId, string $guardianId, string $programmeId, ?string $policyId, ?string $sessionId, CommunicationType $type, string $message, CalendarDate $dueOn, CommunicationStatus $status, string $createdBy, DateTimeImmutable $createdAt, string $updatedBy, DateTimeImmutable $updatedAt, string $contactReference, int $version): self
    {
        return new self($id, $childId, $authorityId, $guardianId, $programmeId, $policyId, $sessionId, $type, $message, $dueOn, $status, $createdBy, $createdAt, $updatedBy, $updatedAt, $contactReference, $version);
    }

    /** Records contact or cancels a draft. A reference (or cancellation reason) is required. */
    public function finish(int $expectedVersion, ?string $next, ?string $reference, string $actor, DateTimeImmutable $now): self
    {
        ChildrenRules::version($this->version, $expectedVersion);
        if (CommunicationStatus::Draft !== $this->status) {
            throw ChildrenException::conflict('Only draft communications can be completed or cancelled.');
        }
        $status = null === $next ? null : CommunicationStatus::tryFrom($next);
        if (null === $status || CommunicationStatus::Draft === $status) {
            throw ChildrenException::invalid('Record contact or cancel this draft.');
        }

        return new self($this->id, $this->childId, $this->authorityId, $this->guardianId, $this->programmeId, $this->policyId, $this->sessionId, $this->type, $this->message, $this->dueOn, $status, $this->createdBy, $this->createdAt, $actor, $now, ChildrenRules::text($reference, 500), $this->version + 1);
    }

    public function __debugInfo(): array
    {
        return ['id' => $this->id, 'status' => $this->status->value];
    }
}

<?php

declare(strict_types=1);

namespace App\ChildrenMinistry\Incidents\Domain;

use App\ChildrenMinistry\Shared\Domain\ChildrenException;
use App\ChildrenMinistry\Shared\Domain\ChildrenRules;
use App\Shared\Domain\CalendarDate;
use DateTimeImmutable;

/** A restricted record of something that happened to a child in a session. Metadata only: the account is {@see IncidentDetails}. */
final readonly class Incident
{
    private const string INVALID = 'Choose a valid incident category, severity, review date and retention date.';

    private function __construct(
        public string $id,
        public string $childId,
        public string $sessionId,
        public string $segmentId,
        public IncidentCategory $category,
        public IncidentSeverity $severity,
        public DateTimeImmutable $occurredAt,
        public string $reportedBy,
        public DateTimeImmutable $reportedAt,
        public IncidentStatus $status,
        public ?string $ownerId,
        public CalendarDate $reviewOn,
        public CalendarDate $retainUntil,
        public int $version,
    ) {
        if ($occurredAt > $reportedAt || $retainUntil->isBefore($reviewOn) || (IncidentStatus::Open !== $status && null === $ownerId) || $version < 0) {
            throw ChildrenException::invalid(self::INVALID);
        }
    }

    public static function report(
        string $id,
        string $childId,
        string $sessionId,
        string $segmentId,
        ?string $category,
        ?string $severity,
        DateTimeImmutable $occurredAt,
        string $reportedBy,
        DateTimeImmutable $reportedAt,
        CalendarDate $reviewOn,
        CalendarDate $retainUntil,
    ): self {
        $category = null === $category ? null : IncidentCategory::tryFrom($category);
        $severity = null === $severity ? null : IncidentSeverity::tryFrom($severity);
        if (null === $category || null === $severity) {
            throw ChildrenException::invalid(self::INVALID);
        }

        return new self($id, $childId, $sessionId, $segmentId, $category, $severity, $occurredAt, $reportedBy, $reportedAt, IncidentStatus::Open, null, $reviewOn, $retainUntil, 0);
    }

    public static function reconstitute(
        string $id,
        string $childId,
        string $sessionId,
        string $segmentId,
        IncidentCategory $category,
        IncidentSeverity $severity,
        DateTimeImmutable $occurredAt,
        string $reportedBy,
        DateTimeImmutable $reportedAt,
        IncidentStatus $status,
        ?string $ownerId,
        CalendarDate $reviewOn,
        CalendarDate $retainUntil,
        int $version,
    ): self {
        return new self($id, $childId, $sessionId, $segmentId, $category, $severity, $occurredAt, $reportedBy, $reportedAt, $status, $ownerId, $reviewOn, $retainUntil, $version);
    }

    /** Assigns responsibility and a review date; the incident moves to review. */
    public function triage(int $expectedVersion, ?string $owner, ?CalendarDate $review, CalendarDate $on): self
    {
        ChildrenRules::version($this->version, $expectedVersion);
        $this->requireNotClosed();
        if (null === $owner || null === $review || $review->isBefore($on)) {
            throw ChildrenException::invalid('Assign responsibility and a current or future review date.');
        }

        return $this->with(IncidentStatus::InReview, $owner, $review);
    }

    /** Records an action, a guardian contact, closure or reopening. Closing needs an owner; only closed incidents reopen. */
    public function progress(int $expectedVersion, IncidentEventType $action, CalendarDate $on): self
    {
        ChildrenRules::version($this->version, $expectedVersion);
        if (!\in_array($action, [IncidentEventType::Action, IncidentEventType::GuardianContact, IncidentEventType::Closed, IncidentEventType::Reopened], true)) {
            throw ChildrenException::invalid('Choose a valid follow-up action.');
        }
        if (IncidentEventType::Reopened === $action) {
            if (IncidentStatus::Closed !== $this->status) {
                throw ChildrenException::conflict('Only closed incidents can be reopened.');
            }

            return $this->with(IncidentStatus::InReview, $this->ownerId, $on);
        }
        $this->requireNotClosed();
        if (IncidentEventType::Closed === $action && null === $this->ownerId) {
            throw ChildrenException::conflict('Assign responsibility before closing this incident.');
        }

        return $this->with(IncidentEventType::Closed === $action ? IncidentStatus::Closed : $this->status, $this->ownerId, $this->reviewOn);
    }

    private function requireNotClosed(): void
    {
        if (IncidentStatus::Closed === $this->status) {
            throw ChildrenException::conflict('Reopen this incident before recording further follow-up.');
        }
    }

    private function with(IncidentStatus $status, ?string $owner, CalendarDate $review): self
    {
        return new self($this->id, $this->childId, $this->sessionId, $this->segmentId, $this->category, $this->severity, $this->occurredAt, $this->reportedBy, $this->reportedAt, $status, $owner, $review, $this->retainUntil, $this->version + 1);
    }
}

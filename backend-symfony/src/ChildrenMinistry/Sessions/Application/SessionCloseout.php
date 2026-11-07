<?php

declare(strict_types=1);

namespace App\ChildrenMinistry\Sessions\Application;

use DateTimeImmutable;

/** Counts over every visit segment of the session, never over a roster page. Carries no codes or private care data. */
final readonly class SessionCloseout
{
    public function __construct(
        public int $visits,
        public int $children,
        public int $awaitingCollection,
        public int $collected,
        public int $transferredOut,
        public int $labelsNeedingAttention,
        public int $failedHandoverAttempts,
        public int $supervisorCollections,
        public DateTimeImmutable $checkedAt,
        public ?IncidentCounts $incidents = null,
    ) {
    }

    public function withIncidents(IncidentCounts $counts): self
    {
        return new self($this->visits, $this->children, $this->awaitingCollection, $this->collected, $this->transferredOut, $this->labelsNeedingAttention, $this->failedHandoverAttempts, $this->supervisorCollections, $this->checkedAt, $counts);
    }
}

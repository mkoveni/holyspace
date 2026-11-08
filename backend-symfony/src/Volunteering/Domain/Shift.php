<?php

declare(strict_types=1);

namespace App\Volunteering\Domain;

use DateTimeImmutable;

/**
 * A duty of one team role over at most seven days, standalone or linked to a source activity (an event occurrence, a
 * children session). The version moves with every change, including bookings; the schedule revision moves only when
 * the duty itself changes, so an invitation can tell a changed duty from a busier one.
 */
final readonly class Shift
{
    public const array STATUSES = ['OPEN', 'CANCELLED'];

    private const int MAX_SECONDS = 7 * 86400;

    private function __construct(
        public string $id,
        public string $requestId,
        public string $requestHash,
        public string $teamId,
        public string $roleId,
        public string $sourceKind,
        public ?string $sourceId,
        public ?string $sourceRevision,
        public DateTimeImmutable $startsAt,
        public DateTimeImmutable $endsAt,
        public int $positions,
        public string $status,
        public int $version,
        public int $scheduleRevision,
    ) {
        ServingRules::period($startsAt, $endsAt);
        if ($endsAt > $startsAt->modify('+'.self::MAX_SECONDS.' seconds') || $positions < 1 || $positions > 100 || !\in_array($status, self::STATUSES, true)
            || '' === $sourceKind || mb_strlen($sourceKind) > 40 || (('STANDALONE' === $sourceKind) !== (null === $sourceId))) {
            throw VolunteerException::invalid('Choose a source, at most seven days and 1–100 positions.');
        }
    }

    public static function plan(
        string $id,
        string $requestId,
        string $requestHash,
        string $teamId,
        string $roleId,
        ?string $sourceKind,
        ?string $sourceId,
        ?DateTimeImmutable $startsAt,
        ?DateTimeImmutable $endsAt,
        int $positions,
    ): self {
        return new self($id, $requestId, $requestHash, $teamId, $roleId, (string) $sourceKind, $sourceId, null, ServingRules::instant($startsAt), ServingRules::instant($endsAt), $positions, 'OPEN', 0, 0);
    }

    public static function reconstitute(
        string $id,
        string $requestId,
        string $requestHash,
        string $teamId,
        string $roleId,
        string $sourceKind,
        ?string $sourceId,
        ?string $sourceRevision,
        DateTimeImmutable $startsAt,
        DateTimeImmutable $endsAt,
        int $positions,
        string $status,
        int $version,
        int $scheduleRevision,
    ): self {
        return new self($id, $requestId, $requestHash, $teamId, $roleId, $sourceKind, $sourceId, $sourceRevision, $startsAt, $endsAt, $positions, $status, $version, $scheduleRevision);
    }

    /** Revises the duty (or cancels it with status CANCELLED); moves both the version and the schedule revision. */
    public function change(?DateTimeImmutable $from, ?DateTimeImmutable $until, int $positions, ?string $status): self
    {
        if ($this->isCancelled()) {
            throw VolunteerException::conflict('Cancelled shifts cannot reopen.');
        }

        return new self($this->id, $this->requestId, $this->requestHash, $this->teamId, $this->roleId, $this->sourceKind, $this->sourceId, $this->sourceRevision, ServingRules::instant($from), ServingRules::instant($until), $positions, (string) $status, $this->version + 1, $this->scheduleRevision + 1);
    }

    /** Records the linked activity's schedule fingerprint the duty was planned against. */
    public function withSourceRevision(?string $revision): self
    {
        return new self($this->id, $this->requestId, $this->requestHash, $this->teamId, $this->roleId, $this->sourceKind, $this->sourceId, $revision, $this->startsAt, $this->endsAt, $this->positions, $this->status, $this->version, $this->scheduleRevision);
    }

    /** A booking change makes an open shift screen stale without changing the duty. */
    public function touch(): self
    {
        return new self($this->id, $this->requestId, $this->requestHash, $this->teamId, $this->roleId, $this->sourceKind, $this->sourceId, $this->sourceRevision, $this->startsAt, $this->endsAt, $this->positions, $this->status, $this->version + 1, $this->scheduleRevision);
    }

    public function isCancelled(): bool
    {
        return 'CANCELLED' === $this->status;
    }

    public function isOpen(): bool
    {
        return 'OPEN' === $this->status;
    }
}

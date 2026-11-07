<?php

declare(strict_types=1);

namespace App\ChildrenMinistry\Sessions\Domain;

use App\ChildrenMinistry\Shared\Domain\ChildrenException;
use App\ChildrenMinistry\Shared\Domain\ChildrenRules;
use DateTimeImmutable;

/**
 * One visit of a child. The visit stays one record across transfers between sessions; only collection ends it.
 * The pickup code itself is never stored, only its hash.
 */
final readonly class Attendance
{
    public const int MAX_FAILED_ATTEMPTS = 5;
    public const int ATTEMPT_WINDOW_SECONDS = 900;

    private function __construct(
        public string $id,
        public string $childId,
        public string $sessionId,
        public DateTimeImmutable $arrivedAt,
        public string $arrivedBy,
        public string $arrivalRequestId,
        public ?DateTimeImmutable $checkedOutAt,
        public ?string $checkedOutBy,
        public ?string $collectorId,
        public ?string $checkoutRequestId,
        public ?string $authorizationId,
        public bool $identityVerified,
        public ?string $exceptionReason,
        public ?string $labelHash,
        public int $labelVersion,
        public ?DateTimeImmutable $labelExpiresAt,
        public int $failedAttempts,
        public ?DateTimeImmutable $attemptsResetAt,
        public int $version,
    ) {
    }

    public static function arrive(string $id, string $childId, string $sessionId, DateTimeImmutable $at, string $by, string $requestId): self
    {
        return new self($id, $childId, $sessionId, $at, $by, $requestId, null, null, null, null, null, false, null, null, 0, null, 0, null, 0);
    }

    public static function reconstitute(
        string $id,
        string $childId,
        string $sessionId,
        DateTimeImmutable $arrivedAt,
        string $arrivedBy,
        string $arrivalRequestId,
        ?DateTimeImmutable $checkedOutAt,
        ?string $checkedOutBy,
        ?string $collectorId,
        ?string $checkoutRequestId,
        ?string $authorizationId,
        bool $identityVerified,
        ?string $exceptionReason,
        ?string $labelHash,
        int $labelVersion,
        ?DateTimeImmutable $labelExpiresAt,
        int $failedAttempts,
        ?DateTimeImmutable $attemptsResetAt,
        int $version,
    ): self {
        return new self($id, $childId, $sessionId, $arrivedAt, $arrivedBy, $arrivalRequestId, $checkedOutAt, $checkedOutBy, $collectorId, $checkoutRequestId, $authorizationId, $identityVerified, $exceptionReason, $labelHash, $labelVersion, $labelExpiresAt, $failedAttempts, $attemptsResetAt, $version);
    }

    public function requireOpen(): void
    {
        if (null !== $this->checkedOutAt) {
            throw ChildrenException::conflict('Child has already been collected.');
        }
    }

    /** A new label replaces the old one; replacing needs a reason. Issuing never moves the failed-attempt counter. */
    public function issueLabel(string $hash, DateTimeImmutable $expires, int $expectedVersion, ?string $reason): self
    {
        $this->requireOpen();
        ChildrenRules::version($this->version, $expectedVersion);
        if ($this->labelVersion > 0) {
            ChildrenRules::text($reason, 500);
        }

        return $this->with(labelHash: $hash, labelVersion: $this->labelVersion + 1, labelExpiresAt: $expires, version: $this->version + 1);
    }

    /** The fifth failure inside a 15 minute window locks handover until the window ends; a later failure starts a new window. */
    public function failedAttempt(DateTimeImmutable $now): self
    {
        $reset = null === $this->attemptsResetAt || $now >= $this->attemptsResetAt;

        return $this->with(
            failedAttempts: $reset ? 1 : $this->failedAttempts + 1,
            attemptsResetAt: $reset ? $now->modify('+'.self::ATTEMPT_WINDOW_SECONDS.' seconds') : $this->attemptsResetAt,
            version: $this->version + 1,
        );
    }

    public function throttled(DateTimeImmutable $now): bool
    {
        return $this->failedAttempts >= self::MAX_FAILED_ATTEMPTS && null !== $this->attemptsResetAt && $now < $this->attemptsResetAt;
    }

    /** Collection clears the label hash and ends the visit. */
    public function collect(string $collector, string $operator, string $requestId, string $authorization, bool $verified, ?string $reason, int $expectedVersion, DateTimeImmutable $now): self
    {
        $this->requireOpen();
        ChildrenRules::version($this->version, $expectedVersion);
        if (!$verified) {
            throw ChildrenException::invalid('Confirm collector identity before handover.');
        }
        if ($now < $this->arrivedAt) {
            throw ChildrenException::invalid('Handover cannot predate arrival.');
        }

        return $this->with(checkedOutAt: $now, checkedOutBy: $operator, collectorId: $collector, checkoutRequestId: $requestId, authorizationId: $authorization, identityVerified: true, exceptionReason: ChildrenRules::optional($reason, 500), labelHash: null, clearLabel: true, version: $this->version + 1);
    }

    /** A transfer invalidates the label: the new room needs its own. */
    public function transfer(string $targetSession, int $expectedVersion): self
    {
        $this->requireOpen();
        ChildrenRules::version($this->version, $expectedVersion);
        if ($this->sessionId === $targetSession) {
            throw ChildrenException::invalid('Select a different target session.');
        }

        return $this->with(sessionId: $targetSession, labelHash: null, clearLabel: true, labelVersion: $this->labelVersion + 1, labelExpiresAt: null, clearExpiry: true, version: $this->version + 1);
    }

    private function with(
        ?string $sessionId = null,
        ?DateTimeImmutable $checkedOutAt = null,
        ?string $checkedOutBy = null,
        ?string $collectorId = null,
        ?string $checkoutRequestId = null,
        ?string $authorizationId = null,
        ?bool $identityVerified = null,
        ?string $exceptionReason = null,
        ?string $labelHash = null,
        bool $clearLabel = false,
        ?int $labelVersion = null,
        ?DateTimeImmutable $labelExpiresAt = null,
        bool $clearExpiry = false,
        ?int $failedAttempts = null,
        ?DateTimeImmutable $attemptsResetAt = null,
        ?int $version = null,
    ): self {
        return new self(
            $this->id,
            $this->childId,
            $sessionId ?? $this->sessionId,
            $this->arrivedAt,
            $this->arrivedBy,
            $this->arrivalRequestId,
            $checkedOutAt ?? $this->checkedOutAt,
            $checkedOutBy ?? $this->checkedOutBy,
            $collectorId ?? $this->collectorId,
            $checkoutRequestId ?? $this->checkoutRequestId,
            $authorizationId ?? $this->authorizationId,
            $identityVerified ?? $this->identityVerified,
            $exceptionReason ?? $this->exceptionReason,
            $clearLabel ? null : ($labelHash ?? $this->labelHash),
            $labelVersion ?? $this->labelVersion,
            $clearExpiry ? null : ($labelExpiresAt ?? $this->labelExpiresAt),
            $failedAttempts ?? $this->failedAttempts,
            $attemptsResetAt ?? $this->attemptsResetAt,
            $version ?? $this->version,
        );
    }

    /** Never exposes the label hash in logs or dumps. */
    public function __debugInfo(): array
    {
        return ['id' => $this->id, 'version' => $this->version];
    }
}

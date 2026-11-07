<?php

declare(strict_types=1);

namespace App\Giving\Domain;

use App\Shared\Domain\CalendarDate;
use DateTimeImmutable;

/**
 * A donor's commitment to give a target amount to one fund within a period of at most ten years. Gifts never fulfil
 * it automatically: each one is an explicit PledgeAssignment. Cancelling keeps the commitment and its assignments as evidence.
 */
final readonly class Pledge
{
    public const string OPEN = 'OPEN';
    public const string CANCELLED = 'CANCELLED';
    public const array STATUSES = [self::OPEN, self::CANCELLED];
    public const int MAX_PERIOD_DAYS = 3660;

    private function __construct(
        public string $id,
        public string $donorId,
        public string $fundId,
        public Money $target,
        public CalendarDate $startsOn,
        public CalendarDate $endsOn,
        public string $status,
        public int $version,
        public ?string $cancellationReason,
        public string $createdBy,
        public DateTimeImmutable $createdAt,
    ) {
    }

    /** Java's record constructor: every rule shares one message. */
    private static function checked(
        string $id,
        ?string $donorId,
        ?string $fundId,
        Money $target,
        ?CalendarDate $startsOn,
        ?CalendarDate $endsOn,
        string $status,
        int $version,
        ?string $cancellationReason,
        string $createdBy,
        DateTimeImmutable $createdAt,
    ): self {
        if ('' === $id || null === $donorId || '' === $donorId || null === $fundId || '' === $fundId
            || null === $startsOn || null === $endsOn || $endsOn->year() > 9999 || $endsOn->isBefore($startsOn)
            || $startsOn->year() < 1900 || $startsOn->daysUntil($endsOn) > self::MAX_PERIOD_DAYS || $version < 0
            || !\in_array($status, self::STATUSES, true)) {
            throw GivingException::invalid('A pledge needs a donor, fund, currency and a commitment period of at most ten years.');
        }

        return new self($id, $donorId, $fundId, $target, $startsOn, $endsOn, $status, $version, $cancellationReason, $createdBy, $createdAt);
    }

    /** The request id becomes the pledge id, so a retried create finds the row it wrote. */
    public static function create(
        string $id,
        ?string $donorId,
        ?string $fundId,
        Money $target,
        ?CalendarDate $startsOn,
        ?CalendarDate $endsOn,
        string $createdBy,
        DateTimeImmutable $createdAt,
    ): self {
        return self::checked($id, $donorId, $fundId, $target, $startsOn, $endsOn, self::OPEN, 0, null, $createdBy, $createdAt);
    }

    public static function reconstitute(
        string $id,
        string $donorId,
        string $fundId,
        Money $target,
        CalendarDate $startsOn,
        CalendarDate $endsOn,
        string $status,
        int $version,
        ?string $cancellationReason,
        string $createdBy,
        DateTimeImmutable $createdAt,
    ): self {
        return self::checked($id, $donorId, $fundId, $target, $startsOn, $endsOn, $status, $version, $cancellationReason, $createdBy, $createdAt);
    }

    /** Version first, then open, then the reason, in Java's order. */
    public function cancel(int $expectedVersion, ?string $reason): self
    {
        GivingRules::version($this->version, $expectedVersion);
        $this->requireOpen();

        return new self(
            $this->id,
            $this->donorId,
            $this->fundId,
            $this->target,
            $this->startsOn,
            $this->endsOn,
            self::CANCELLED,
            $this->version + 1,
            GivingRules::text($reason, 1000, 'Cancellation reason'),
            $this->createdBy,
            $this->createdAt,
        );
    }

    /** The next version with nothing else changed: every assignment change advances its pledge, so stale screens are refused. */
    public function revised(): self
    {
        return new self(
            $this->id,
            $this->donorId,
            $this->fundId,
            $this->target,
            $this->startsOn,
            $this->endsOn,
            $this->status,
            $this->version + 1,
            $this->cancellationReason,
            $this->createdBy,
            $this->createdAt,
        );
    }

    public function requireOpen(): void
    {
        if (self::OPEN !== $this->status) {
            throw GivingException::conflict('This pledge is cancelled.');
        }
    }

    /**
     * Credits part of a gift's allocation to this pledge (Java `validateAssignment`, then the assignment record). The gift
     * must be the donor's, in the pledge currency and received within the period; the amount may not exceed what is still
     * available on the gift's allocation to this fund ($availableMinor) or what remains of the target after $creditedMinor.
     */
    public function assign(
        Gift $gift,
        string $requestId,
        int $amountMinor,
        int $creditedMinor,
        int $availableMinor,
        string $actorId,
        DateTimeImmutable $now,
    ): PledgeAssignment {
        $this->requireOpen();
        Money::of($amountMinor, $this->target->currency);
        if ($this->donorId !== $gift->donorId || $this->target->currency !== $gift->money->currency
            || $gift->receivedOn->isBefore($this->startsOn) || $gift->receivedOn->isAfter($this->endsOn)) {
            throw GivingException::invalid('Choose a gift from this donor, currency and commitment period.');
        }
        if ($amountMinor > $availableMinor || $amountMinor > $this->target->amountMinor - $creditedMinor) {
            throw GivingException::conflict('The assignment exceeds the available gift allocation or remaining pledge target.');
        }

        return PledgeAssignment::record(
            $requestId,
            $this->id,
            $gift->id,
            $this->fundId,
            $this->donorId,
            $this->target->currency,
            $amountMinor,
            $actorId,
            $now,
        );
    }
}

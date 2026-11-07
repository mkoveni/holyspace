<?php

declare(strict_types=1);

namespace App\Giving\Domain;

use DateTimeImmutable;

/**
 * An explicit credit of part of one gift allocation to a pledge. It is never deleted: cancelling keeps the original
 * evidence and adds who cancelled it, when and why, which releases the allocation for another pledge.
 */
final readonly class PledgeAssignment
{
    public const string ACTIVE = 'ACTIVE';
    public const string CANCELLED = 'CANCELLED';

    public ?string $cancellationReason;

    private function __construct(
        public string $id,
        public string $pledgeId,
        public string $giftId,
        public string $fundId,
        public string $donorId,
        public string $currency,
        public int $amountMinor,
        public string $status,
        public int $version,
        ?string $cancellationReason,
        public ?string $cancelledBy,
        public ?DateTimeImmutable $cancelledAt,
        public string $createdBy,
        public DateTimeImmutable $createdAt,
    ) {
        Money::of($amountMinor, $currency);
        if ('' === $id || '' === $pledgeId || '' === $giftId || '' === $fundId || '' === $donorId || '' === $createdBy
            || $version < 0 || !\in_array($status, [self::ACTIVE, self::CANCELLED], true)) {
            throw GivingException::invalid('Invalid pledge assignment evidence.');
        }
        if (self::CANCELLED === $status) {
            $cancellationReason = GivingRules::text($cancellationReason, 1000, 'Cancellation reason');
            if (null === $cancelledBy || null === $cancelledAt) {
                throw GivingException::invalid('Cancellation evidence needs its actor and time.');
            }
        }
        $this->cancellationReason = $cancellationReason;
    }

    /** Created only through Pledge::assign, which applies the donor, period and capacity rules. */
    public static function record(
        string $id,
        string $pledgeId,
        string $giftId,
        string $fundId,
        string $donorId,
        string $currency,
        int $amountMinor,
        string $createdBy,
        DateTimeImmutable $createdAt,
    ): self {
        return new self($id, $pledgeId, $giftId, $fundId, $donorId, $currency, $amountMinor, self::ACTIVE, 0, null, null, null, $createdBy, $createdAt);
    }

    public static function reconstitute(
        string $id,
        string $pledgeId,
        string $giftId,
        string $fundId,
        string $donorId,
        string $currency,
        int $amountMinor,
        string $status,
        int $version,
        ?string $cancellationReason,
        ?string $cancelledBy,
        ?DateTimeImmutable $cancelledAt,
        string $createdBy,
        DateTimeImmutable $createdAt,
    ): self {
        return new self($id, $pledgeId, $giftId, $fundId, $donorId, $currency, $amountMinor, $status, $version, $cancellationReason, $cancelledBy, $cancelledAt, $createdBy, $createdAt);
    }

    public function cancel(int $expectedVersion, ?string $reason, string $actorId, DateTimeImmutable $at): self
    {
        GivingRules::version($this->version, $expectedVersion);
        if (self::ACTIVE !== $this->status) {
            throw GivingException::conflict('This pledge assignment has already been cancelled.');
        }

        return new self(
            $this->id,
            $this->pledgeId,
            $this->giftId,
            $this->fundId,
            $this->donorId,
            $this->currency,
            $this->amountMinor,
            self::CANCELLED,
            $this->version + 1,
            GivingRules::text($reason, 1000, 'Cancellation reason'),
            $actorId,
            $at,
            $this->createdBy,
            $this->createdAt,
        );
    }
}

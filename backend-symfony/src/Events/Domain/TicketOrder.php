<?php

declare(strict_types=1);

namespace App\Events\Domain;

use DateTimeImmutable;

/** A public buyer's order. There is deliberately no person here: a buyer is a name and an email, nothing more. */
final readonly class TicketOrder
{
    public const array STATUSES = ['RESERVED', 'PAID', 'EXPIRED', 'RELEASED', 'LATE_PAID_REVIEW', 'PARTIALLY_REFUNDED', 'REFUNDED', 'REFUND_REVIEW'];
    /** States in which the buyer holds valid tickets. */
    public const array HOLDING = ['PAID', 'PARTIALLY_REFUNDED', 'REFUND_REVIEW'];

    /** @param list<TicketOrderLine> $lines */
    public function __construct(
        public string $id,
        public string $occurrenceId,
        public string $status,
        public string $buyerName,
        public string $buyerEmailEncrypted,
        public string $buyerEmailHash,
        public string $accessHash,
        public string $walletHash,
        public string $requestHash,
        public int $subtotalMinor,
        public int $feeMinor,
        public int $totalMinor,
        public DateTimeImmutable $expiresAt,
        public DateTimeImmutable $createdAt,
        public ?DateTimeImmutable $paidAt,
        public int $version,
        public array $lines,
    ) {
        if (!\in_array($status, self::STATUSES, true) || $totalMinor !== $subtotalMinor + $feeMinor) {
            throw EventException::invalid('Invalid ticket order.');
        }
    }

    public function seats(): int
    {
        return array_sum(array_map(static fn (TicketOrderLine $l): int => $l->quantity, $this->lines));
    }

    public function isFree(): bool
    {
        return 0 === $this->totalMinor;
    }
}

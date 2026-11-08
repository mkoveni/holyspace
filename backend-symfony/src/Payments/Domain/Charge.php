<?php

declare(strict_types=1);

namespace App\Payments\Domain;

use App\Shared\Domain\CalendarDate;
use DateTimeImmutable;

/** Verified provider charge evidence. Immutable except for the one-time link to its Giving gift (`payments_guard_charge`). */
final readonly class Charge
{
    public function __construct(
        public string $id,
        public string $orderId,
        public string $provider,
        public string $mode,
        public string $reference,
        public int $amountMinor,
        public CalendarDate $receivedOn,
        public ?string $giftId,
        public DateTimeImmutable $createdAt,
    ) {
    }

    public function isTest(): bool
    {
        return 'test' === $this->mode;
    }
}

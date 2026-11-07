<?php

declare(strict_types=1);

namespace App\Giving\Domain;

use App\Shared\Domain\CalendarDate;
use DateTimeImmutable;

/** Immutable evidence that a whole gift was reversed on a date; the original gift is never changed. */
final readonly class Reversal
{
    public string $reason;

    private function __construct(
        public string $id,
        public string $giftId,
        public CalendarDate $reversedOn,
        ?string $reason,
        public string $createdBy,
        public DateTimeImmutable $createdAt,
    ) {
        if ('' === $id || '' === $giftId || '' === $createdBy) {
            throw GivingException::invalid('Complete the reversal details.');
        }
        $this->reason = GivingRules::text($reason, 1000, 'Reversal reason');
    }

    /** Created only through Gift::reverse, which applies the chronology rule. */
    public static function record(string $id, string $giftId, CalendarDate $reversedOn, ?string $reason, string $createdBy, DateTimeImmutable $createdAt): self
    {
        return new self($id, $giftId, $reversedOn, $reason, $createdBy, $createdAt);
    }

    public static function reconstitute(string $id, string $giftId, CalendarDate $reversedOn, string $reason, string $createdBy, DateTimeImmutable $createdAt): self
    {
        return new self($id, $giftId, $reversedOn, $reason, $createdBy, $createdAt);
    }
}

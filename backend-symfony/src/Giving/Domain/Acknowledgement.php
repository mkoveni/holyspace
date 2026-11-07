<?php

declare(strict_types=1);

namespace App\Giving\Domain;

use App\Shared\Domain\CalendarDate;
use DateTimeImmutable;

/**
 * Immutable numbered evidence that a gift was acknowledged. Donor and fund names are frozen at issue; a later reversal
 * makes it void without changing it (the database rejects updates and deletes and checks the lines against the gift).
 */
final readonly class Acknowledgement
{
    public string $donorName;
    /** @var list<AcknowledgementLine> */
    public array $lines;

    /** @param list<AcknowledgementLine> $lines */
    private function __construct(
        public string $id,
        public string $giftId,
        ?string $donorName,
        public CalendarDate $issuedOn,
        public string $createdBy,
        public DateTimeImmutable $createdAt,
        array $lines,
    ) {
        $this->donorName = GivingRules::text($donorName, 300, 'Donor name');
        $this->lines = $lines;
    }

    /**
     * Freezes one line per allocation of the gift, in the gift's allocation order, named from $fundNames (fund id => name).
     * The request id becomes the acknowledgement id, so a retry finds the row it wrote.
     *
     * @param array<string, string> $fundNames
     */
    public static function issue(
        string $id,
        Gift $gift,
        ?string $donorName,
        array $fundNames,
        CalendarDate $issuedOn,
        string $createdBy,
        DateTimeImmutable $createdAt,
    ): self {
        $lines = array_map(
            static fn (Allocation $allocation): AcknowledgementLine => new AcknowledgementLine(
                $allocation->fundId,
                $fundNames[$allocation->fundId] ?? throw GivingException::missing(),
                $allocation->amountMinor,
            ),
            $gift->allocations,
        );

        return new self($id, $gift->id, $donorName, $issuedOn, $createdBy, $createdAt, $lines);
    }
}

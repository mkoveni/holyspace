<?php

declare(strict_types=1);

namespace App\Giving\Application\Contract;

use App\Shared\Domain\CalendarDate;
use DateTimeImmutable;

/** A donor statement or ledger export (Java GivingLedgerReader.Report): every line of the period plus per-currency totals. */
final readonly class LedgerReport
{
    /**
     * @param list<LedgerLine>  $lines
     * @param list<GivingTotal> $totals
     */
    public function __construct(
        public ?string $donorId,
        public string $donorName,
        public CalendarDate $from,
        public CalendarDate $to,
        public DateTimeImmutable $generatedAt,
        public array $lines,
        public array $totals,
    ) {
    }
}

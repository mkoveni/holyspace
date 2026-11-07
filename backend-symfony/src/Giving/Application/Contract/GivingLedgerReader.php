<?php

declare(strict_types=1);

namespace App\Giving\Application\Contract;

use App\Giving\Domain\GivingException;

/** Allocation-level ledger lines for statements and exports (Java GivingLedgerReader). */
interface GivingLedgerReader
{
    /**
     * Lines of a validated filter, ordered by date, gift, kind and fund. A fund filter keeps only that fund's
     * allocations; dates are the received, reversal or refund date of each line.
     *
     * @return list<LedgerLine>
     *
     * @throws GivingException conflict when more than $maximum lines match: a report never truncates silently
     */
    public function lines(GiftFilter $filter, int $maximum): array;
}

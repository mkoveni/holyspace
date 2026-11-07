<?php

declare(strict_types=1);

namespace App\Giving\Application\Query\GetDonorStatement;

use App\Giving\Application\Contract\GiftFilter;
use App\Giving\Application\Contract\LedgerReport;
use App\Giving\Application\GivingAccess;
use App\Giving\Application\GivingReports;
use App\Giving\Domain\GivingException;

/** A printable current-record statement for one donor; not a tax certificate. */
final readonly class GetDonorStatementHandler
{
    public function __construct(
        private GivingAccess $access,
        private GivingReports $reports,
    ) {
    }

    public function handle(GetDonorStatementQuery $query): LedgerReport
    {
        $this->access->read();
        if (null === $query->donorId) {
            throw GivingException::invalid('Choose a donor for the statement.');
        }

        return $this->reports->report(new GiftFilter($query->from, $query->to, null, null, $query->donorId, 25, 0));
    }
}

<?php

declare(strict_types=1);

namespace App\Giving\Application\Query\ExportGivingLedger;

use App\Giving\Application\Contract\LedgerReport;
use App\Giving\Application\GivingAccess;
use App\Giving\Application\GivingReports;

/** The filtered ledger for export; needs giving.read and giving.export. */
final readonly class ExportGivingLedgerHandler
{
    public function __construct(
        private GivingAccess $access,
        private GivingReports $reports,
    ) {
    }

    public function handle(ExportGivingLedgerQuery $query): LedgerReport
    {
        $this->access->export();

        return $this->reports->report($query->filter);
    }
}

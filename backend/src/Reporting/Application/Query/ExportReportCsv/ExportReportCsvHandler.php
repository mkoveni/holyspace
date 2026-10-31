<?php

declare(strict_types=1);

namespace App\Reporting\Application\Query\ExportReportCsv;

use App\Reporting\Application\Query\FormatReportCsv\FormatReportCsvHandler;
use App\Reporting\Application\Query\FormatReportCsv\FormatReportCsvQuery;

final readonly class ExportReportCsvHandler
{
    public function __construct(private FormatReportCsvHandler $formatReportCsvHandler)
    {
    }

    public function __invoke(ExportReportCsvQuery $message): string
    {
        $rows = $message->rows;

        return ($this->formatReportCsvHandler)(new FormatReportCsvQuery($rows));
    }
}

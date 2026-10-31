<?php

declare(strict_types=1);

namespace App\Reporting\Application\Query\FormatReportCsv;

final readonly class FormatReportCsvHandler
{
    public function __construct()
    {
    }

    public function __invoke(FormatReportCsvQuery $message): string
    {
        $rows = $message->rows;
        $stream = fopen('php://temp', 'r+');
        if ($rows) {
            fputcsv($stream, array_keys($rows[0]), ',', '"', '');
        }
        foreach ($rows as $row) {
            foreach ($row as &$value) {
                if (is_string($value) && preg_match('/^[\s]*[=+\-@]|^[\t\r\n]/u', $value)) {
                    $value = "'".$value;
                }
            }
            unset($value);
            fputcsv($stream, array_values($row), ',', '"', '');
        }
        rewind($stream);
        $csv = stream_get_contents($stream);
        fclose($stream);

        return $csv;
    }
}

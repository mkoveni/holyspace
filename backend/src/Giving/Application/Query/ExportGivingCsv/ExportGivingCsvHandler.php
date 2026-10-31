<?php

declare(strict_types=1);

namespace App\Giving\Application\Query\ExportGivingCsv;

use Symfony\Component\Intl\Currencies;

final readonly class ExportGivingCsvHandler
{
    public function __construct()
    {
    }

    public function __invoke(ExportGivingCsvQuery $message): string
    {
        $report = $message->report;
        $stream = fopen('php://temp', 'r+');
        fputcsv($stream, ['Date', 'Entry type', 'Gift ID', 'Donor ID', 'Donor', 'Fund ID', 'Fund', 'Currency', 'Signed amount minor', 'Signed amount', 'Method'], ',', '"', '');
        foreach ($report['entries'] as $row) {
            $values = [$row['date'], $row['type'], $row['giftId'], $row['donorId'] ?? '', $row['donorName'], $row['fundId'], $row['fundName'], $row['currency'], (string) $row['amountMinor'], $this->decimal($row['amountMinor'], $row['currency']), $row['method']];
            // User-entered text cannot become a spreadsheet formula. Numeric amounts remain numeric.
            foreach ([4, 6] as $index) {
                if (preg_match('/^[\s]*[=+\-@]/u', $values[$index]) || preg_match('/^[\t\r\n]/', $values[$index])) {
                    $values[$index] = "'".$values[$index];
                }
            }
            fputcsv($stream, $values, ',', '"', '');
        }
        rewind($stream);
        $csv = stream_get_contents($stream);
        fclose($stream);

        return $csv;
    }

    private function decimal(int $amount, string $currency): string
    {
        $digits = Currencies::getFractionDigits($currency);
        $scale = 10 ** $digits;
        $absolute = abs($amount);

        return ($amount < 0 ? '-' : '').intdiv($absolute, $scale).(0 === $digits ? '' : '.'.str_pad((string) ($absolute % $scale), $digits, '0', STR_PAD_LEFT));
    }
}

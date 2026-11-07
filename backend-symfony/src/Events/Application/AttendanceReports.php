<?php

declare(strict_types=1);

namespace App\Events\Application;

use App\Events\Application\Contract\EventReportReader;
use App\Events\Application\Contract\ReportPage;
use App\Events\Domain\EventException;
use App\Events\Domain\EventRules;
use DateTimeImmutable;
use Psr\Clock\ClockInterface;

/** Attendance totals per occurrence over a date range, as a page or as a CSV export. */
final readonly class AttendanceReports
{
    private const int EXPORT_LIMIT = 10000;

    public function __construct(private EventReportReader $reader, private ClockInterface $clock)
    {
    }

    public function report(?string $eventId, string $search, DateTimeImmutable $from, DateTimeImmutable $until, int $limit, int $offset): ReportPage
    {
        EventRules::range($from, $until);
        EventRules::page($limit, $offset);
        $term = EventRules::optional($search, 200);
        $now = $this->clock->now();

        return new ReportPage($this->reader->rows($eventId, $term, $from, $until, $now, $limit, $offset), $this->reader->totals($eventId, $term, $from, $until, $now));
    }

    public function export(?string $eventId, string $search, DateTimeImmutable $from, DateTimeImmutable $until): string
    {
        EventRules::range($from, $until);
        $term = EventRules::optional($search, 200);
        $now = $this->clock->now();
        if ($this->reader->totals($eventId, $term, $from, $until, $now)->occurrences > self::EXPORT_LIMIT) {
            throw EventException::invalid('Export at most 10,000 occurrences. Choose a shorter range.');
        }
        $lines = [];
        foreach ($this->reader->rows($eventId, $term, $from, $until, $now, self::EXPORT_LIMIT, 0) as $row) {
            $lines[] = implode(',', [
                self::cell($row->title),
                self::cell($row->occurrenceId),
                self::cell(Fingerprint::instant($row->startsAt)),
                self::cell(Fingerprint::instant($row->endsAt)),
                self::cell($row->timeZone),
                self::cell($row->venue),
                self::cell($row->status),
                (string) $row->counts->confirmed,
                (string) $row->counts->waitlisted,
                (string) $row->counts->cancelled,
                (string) $row->counts->attended,
                (string) $row->counts->absent,
            ]);
        }
        $rows = implode("\r\n", $lines);

        return "Event,Occurrence,Starts at,Ends at,Time zone,Venue,Status,Confirmed,Waitlisted,Cancelled,Attended,Absent\r\n".$rows.('' === $rows ? '' : "\r\n");
    }

    /** Quotes a cell and defuses spreadsheet formulas: a value that opens with = + - or @ gets a leading apostrophe. */
    private static function cell(string $value): string
    {
        $stripped = ltrim($value);
        if ('' !== $stripped && str_contains('=+-@', $stripped[0])) {
            $value = "'".$value;
        }

        return '"'.str_replace('"', '""', $value).'"';
    }
}

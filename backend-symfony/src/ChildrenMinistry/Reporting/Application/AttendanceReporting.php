<?php

declare(strict_types=1);

namespace App\ChildrenMinistry\Reporting\Application;

use App\ChildrenMinistry\Reporting\Application\Contract\AttendanceReportReader;
use App\ChildrenMinistry\Shared\Domain\ChildrenException;
use App\ChildrenMinistry\Shared\Domain\ChildrenRules;
use App\People\Application\Contract\PersonDirectory;
use App\Shared\Application\ChurchCalendar;
use DateTimeImmutable;
use DateTimeZone;
use Psr\Clock\ClockInterface;

/** Read-only attendance reports. Dates are church-local days, at most 366 of them; names are added after filtering and paging. */
final readonly class AttendanceReporting
{
    private const int EXPORT_LIMIT = 10000;

    public function __construct(
        private AttendanceReportReader $reader,
        private PersonDirectory $people,
        private ChurchCalendar $calendar,
        private ClockInterface $clock,
    ) {
    }

    public function report(AttendanceReportFilter $filter, int $limit, int $offset): AttendanceReport
    {
        ChildrenRules::page($limit, $offset);

        return $this->named($this->reader->read($this->criteria($filter), $limit, $offset));
    }

    public function export(AttendanceReportFilter $filter): AttendanceReport
    {
        $page = $this->reader->read($this->criteria($filter), self::EXPORT_LIMIT + 1, 0);
        if (\count($page->visits) > self::EXPORT_LIMIT) {
            throw ChildrenException::invalid('This export exceeds 10,000 visits. Narrow the dates or filters.');
        }

        return $this->named($page);
    }

    /** One attendance's whole journey, oldest room first, independent of any report dates. */
    public function timeline(string $attendanceId, int $limit, int $offset): AttendanceReport
    {
        ChildrenRules::page($limit, $offset);
        $page = $this->reader->read(new AttendanceCriteria(null, null, null, null, null, null, 'ALL', $attendanceId), $limit, $offset);
        if (0 === $page->summary->visits) {
            throw ChildrenException::missing('Attendance');
        }

        return $this->named($page);
    }

    private function criteria(AttendanceReportFilter $filter): AttendanceCriteria
    {
        $days = (new DateTimeImmutable($filter->from->iso(), new DateTimeZone('UTC')))->diff(new DateTimeImmutable($filter->until->iso(), new DateTimeZone('UTC')))->days;
        if ($filter->until->isBefore($filter->from) || $days > 365) {
            throw ChildrenException::invalid('Choose a date range of no more than 366 days.');
        }
        $outcome = $filter->outcome ?? 'ALL';
        if (!\in_array($outcome, ['ALL', 'ATTENDING', 'COLLECTED', 'TRANSFERRED', 'SUPERVISOR'], true)) {
            throw ChildrenException::invalid('Choose a valid attendance outcome.');
        }
        $search = ChildrenRules::optional($filter->search, 120);
        $zone = new DateTimeZone($this->calendar->timeZone());

        return new AttendanceCriteria(
            new DateTimeImmutable($filter->from->iso().' 00:00:00', $zone),
            new DateTimeImmutable($filter->until->plusDays(1)->iso().' 00:00:00', $zone),
            $filter->programmeId,
            $filter->locationId,
            $filter->childId,
            '' === $search ? null : $this->people->matchingIds($search),
            $outcome,
            null,
        );
    }

    private function named(AttendancePage $page): AttendanceReport
    {
        $names = [];
        foreach ($this->people->getAll(array_values(array_unique(array_map(static fn (ReportVisit $v): string => $v->childId, $page->visits)))) as $person) {
            $names[$person->id] = $person->name;
        }

        return new AttendanceReport(
            array_map(static fn (ReportVisit $visit): ReportVisit => $visit->named($names[$visit->childId] ?? 'Unavailable person'), $page->visits),
            $page->summary,
            $this->calendar->timeZone(),
            $this->clock->now(),
        );
    }
}

<?php

declare(strict_types=1);

namespace App\Reporting\Application\Query\BuildReportSummary;

use App\Attendance\Application\Query\GetAttendance\GetAttendanceHandler;
use App\Attendance\Application\Query\GetAttendance\GetAttendanceQuery;
use App\Events\Application\ReadModel\OccurrenceDirectory;
use App\Giving\Application\Query\GetGivingReport\GetGivingReportHandler;
use App\Giving\Application\Query\GetGivingReport\GetGivingReportQuery;
use App\Ministries\Application\ReadModel\MinistriesReadStore;
use App\People\Application\ReadModel\PeopleReportDirectory;
use App\Reporting\Domain\Model\SegmentCriteria;
use DateTimeZone;
use Symfony\Component\Clock\ClockInterface;

final readonly class BuildReportSummaryHandler
{
    public function __construct(
        private PeopleReportDirectory $people,
        private MinistriesReadStore $ministries,
        private OccurrenceDirectory $occurrences,
        private ClockInterface $clock,
        private GetAttendanceHandler $getAttendanceHandler,
        private GetGivingReportHandler $getGivingReportHandler,
    ) {
    }

    public function __invoke(BuildReportSummaryQuery $message): array
    {
        $from = $message->from;
        $to = $message->to;
        $permissions = $message->permissions;
        SegmentCriteria::period($from, $to);
        $result = ['from' => $from, 'to' => $to, 'sections' => [], 'notice' => 'Current corrected records; dates use UTC occurrence starts. Cancelled occurrences are excluded from attendance. Visitor counts are separate from named people.'];
        if (in_array('people.read', $permissions, true)) {
            $counts = [];
            foreach ($this->people->all() as $p) {
                $counts[$p['status']] = ($counts[$p['status']] ?? 0) + 1;
            }
            $result['sections']['people'] = ['byStatus' => $counts, 'total' => array_sum($counts)];
        }
        if (in_array('attendance.manage', $permissions, true)) {
            $daily = [];
            $unique = [];
            foreach ($this->presence($from, $to) as $r) {
                $date = $r['date'];
                $daily[$date] ??= ['date' => $date, 'present' => 0, 'absent' => 0, 'excused' => 0, 'visitors' => 0];
                ++$daily[$date][$r['status']];
                if ('present' === $r['status']) {
                    $unique[$r['personId']] = true;
                }
            }
            $visitors = ($this->getAttendanceHandler)(new GetAttendanceQuery())['visitors'];
            $occurrences = $this->occurrences->getMany(array_column($visitors, 'occurrenceId'));
            foreach ($visitors as $v) {
                $o = $occurrences[$v->occurrenceId] ?? null;
                if (!$o || 'cancelled' === $o['status']) {
                    continue;
                }
                $date = substr($o['startsAt'], 0, 10);
                if ($date < $from || $date > $to) {
                    continue;
                }
                $daily[$date] ??= ['date' => $date, 'present' => 0, 'absent' => 0, 'excused' => 0, 'visitors' => 0];
                $daily[$date]['visitors'] += $v->count;
            }
            ksort($daily);
            $result['sections']['attendance'] = ['days' => array_values($daily), 'uniquePeoplePresent' => count($unique)];
        }
        if (in_array('ministries.manage', $permissions, true)) {
            $groups = [];
            $memberships = $this->ministries->all('memberships');
            foreach ($this->ministries->all('ministries') as $g) {
                $ids = [];
                if ($g->active) {
                    foreach ($memberships as $m) {
                        if ($m->active && $m->ministryId === $g->id && $m->joinedOn <= $this->clock->now()->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d')) {
                            $ids[$m->personId] = true;
                        }
                    }
                }
                $groups[] = ['id' => $g->id, 'name' => $g->name, 'active' => $g->active, 'members' => count($ids)];
            }
            $result['sections']['ministries'] = ['items' => $groups];
        }
        if (in_array('giving.manage', $permissions, true)) {
            $report = ($this->getGivingReportHandler)(new GetGivingReportQuery($from, $to));
            $result['sections']['giving'] = ['totals' => $report['totals'], 'fundTotals' => $report['fundTotals']];
        }

        return $result;
    }

    private function presence(string $from, string $to): array
    {
        $data = ($this->getAttendanceHandler)(new GetAttendanceQuery());
        $rows = [];
        foreach ($data['entries'] as $e) {
            $rows[$e->occurrenceId.':'.$e->personId] = ['occurrenceId' => $e->occurrenceId, 'personId' => $e->personId, 'status' => $e->status];
        }
        foreach ($data['children'] as $e) {
            $rows[$e['occurrenceId'].':'.$e['personId']] = ['occurrenceId' => $e['occurrenceId'], 'personId' => $e['personId'], 'status' => 'present'];
        }
        $selected = [];
        $occurrences = $this->occurrences->getMany(array_column($rows, 'occurrenceId'));
        foreach ($rows as $row) {
            $id = $row['occurrenceId'];
            $o = $occurrences[$id] ?? null;
            if (!$o || 'cancelled' === $o['status']) {
                continue;
            }
            $date = substr($o['startsAt'], 0, 10);
            if ($date >= $from && $date <= $to) {
                $selected[] = $row + ['date' => $date];
            }
        }

        return $selected;
    }
}

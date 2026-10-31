<?php

declare(strict_types=1);

namespace App\Reporting\Application\Query\EvaluateReportCriteria;

use App\Attendance\Application\Query\GetAttendance\GetAttendanceHandler;
use App\Attendance\Application\Query\GetAttendance\GetAttendanceQuery;
use App\Events\Application\ReadModel\OccurrenceDirectory;
use App\Ministries\Application\ReadModel\MinistriesReadStore;
use App\People\Application\ReadModel\PeopleReportDirectory;
use App\Reporting\Domain\Model\SegmentCriteria;
use App\Shared\Domain\ValueObject\Input;
use DateTimeZone;
use InvalidArgumentException;
use OutOfBoundsException;
use Symfony\Component\Clock\ClockInterface;

final readonly class EvaluateReportCriteriaHandler
{
    public function __construct(
        private PeopleReportDirectory $people,
        private MinistriesReadStore $ministries,
        private OccurrenceDirectory $occurrences,
        private ClockInterface $clock,
        private GetAttendanceHandler $getAttendanceHandler,
    ) {
    }

    public function __invoke(EvaluateReportCriteriaQuery $message): array
    {
        $filters = $message->filters;
        SegmentCriteria::validate($filters);
        $today = $this->clock->now()->setTimezone(new DateTimeZone('UTC'))->setTime(0, 0);
        $members = null;
        $presence = [];
        if (isset($filters['ministryId'])) {
            $ministry = $this->ministries->find('ministries', $filters['ministryId']) ?? throw new OutOfBoundsException('Ministry not found.');
            $members = [];
            if ($ministry->active) {
                foreach ($this->ministries->all('memberships') as $m) {
                    if ($m->active && $m->ministryId === $ministry->id && $m->joinedOn <= $today->format('Y-m-d')) {
                        $members[$m->personId] = true;
                    }
                }
            }
        }
        if (isset($filters['attendanceStatus'])) {
            $from = isset($filters['lookbackDays']) ? $today->modify('-'.($filters['lookbackDays'] - 1).' days')->format('Y-m-d') : $filters['from'];
            $to = isset($filters['lookbackDays']) ? $today->format('Y-m-d') : $filters['to'];
            if ($to > $today->format('Y-m-d')) {
                throw new InvalidArgumentException('Attendance segment dates cannot be in the future.');
            }
            foreach ($this->presence($from, $to) as $row) {
                $presence[$row['personId']][$row['status']] = true;
            }
        }
        $items = [];
        foreach ($this->people->all() as $person) {
            if (isset($filters['status']) && $person['status'] !== $filters['status']) {
                continue;
            }
            if (null !== $members && !isset($members[$person['id']])) {
                continue;
            }
            $age = null;
            if ($person['dateOfBirth'] && Input::date($person['dateOfBirth']) <= $today) {
                $age = Input::date($person['dateOfBirth'])->diff($today)->y;
            }
            if (isset($filters['ageGroup']) && (null === $age || ('adult' === $filters['ageGroup'] ? $age < 18 : $age >= 18))) {
                continue;
            }
            $attendance = $filters['attendanceStatus'] ?? null;
            if ('no_recorded_presence' === $attendance && isset($presence[$person['id']]['present'])) {
                continue;
            }
            if (null !== $attendance && 'no_recorded_presence' !== $attendance && !isset($presence[$person['id']][$attendance])) {
                continue;
            }
            $items[] = ['id' => $person['id'], 'name' => $person['name'], 'status' => $person['status'], 'age' => $age];
        }

        return ['items' => $items, 'count' => count($items), 'evaluatedAt' => $this->clock->now()->format(DATE_ATOM), 'notice' => 'Live current records. No recorded presence does not prove absence. Unknown birth dates do not match age filters.'];
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

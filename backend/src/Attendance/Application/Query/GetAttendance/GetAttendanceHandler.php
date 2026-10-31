<?php

declare(strict_types=1);

namespace App\Attendance\Application\Query\GetAttendance;

use App\Attendance\Application\ReadModel\AttendanceReadStore;
use App\ChildrenMinistry\Application\ReadModel\ChildAttendanceDirectory;
use App\Shared\Domain\ValueObject\Input;

final readonly class GetAttendanceHandler
{
    public function __construct(private AttendanceReadStore $records, private ChildAttendanceDirectory $children)
    {
    }

    public function __invoke(GetAttendanceQuery $message): array
    {
        $personId = $message->personId;
        $occurrenceId = $message->occurrenceId;
        if (null !== $personId) {
            Input::id($personId);
        }
        if (null !== $occurrenceId) {
            Input::id($occurrenceId);
        }
        $all = $this->records->all('entries');
        $filter = fn ($entry) => (null === $personId || $entry->personId === $personId) && (null === $occurrenceId || $entry->occurrenceId === $occurrenceId);
        $entries = array_values(array_filter($all, $filter));
        $childEntries = array_values(array_filter($this->children->all(), fn ($e) => (null === $personId || $e['personId'] === $personId) && (null === $occurrenceId || $e['occurrenceId'] === $occurrenceId)));
        $visitors = array_values(array_filter($this->records->all('visitors'), fn ($v) => null === $occurrenceId || $v->occurrenceId === $occurrenceId));
        $presence = [];
        foreach ($entries as $entry) {
            $presence[$entry->occurrenceId][$entry->personId] = $entry->status;
        }
        foreach ($childEntries as $entry) {
            $presence[$entry['occurrenceId']][$entry['personId']] = 'present';
        }
        $totals = [];
        foreach ($presence as $id => $statuses) {
            $totals[$id] = ['id' => $id, 'occurrenceId' => $id, 'present' => count(array_filter($statuses, fn ($s) => 'present' === $s)), 'absent' => count(array_filter($statuses, fn ($s) => 'absent' === $s)), 'excused' => count(array_filter($statuses, fn ($s) => 'excused' === $s)), 'visitors' => 0];
        }
        foreach ($visitors as $visitor) {
            $totals[$visitor->occurrenceId] ??= ['id' => $visitor->occurrenceId, 'occurrenceId' => $visitor->occurrenceId, 'present' => 0, 'absent' => 0, 'excused' => 0, 'visitors' => 0];
            $totals[$visitor->occurrenceId]['visitors'] = null === $personId ? $visitor->count : 0;
        }
        foreach ($totals as &$total) {
            $total['totalPresent'] = $total['present'] + $total['visitors'];
        }

        return ['entries' => $entries, 'children' => $childEntries, 'visitors' => $visitors, 'totals' => array_values($totals)];
    }
}

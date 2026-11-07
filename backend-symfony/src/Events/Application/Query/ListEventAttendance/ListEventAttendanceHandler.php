<?php

declare(strict_types=1);

namespace App\Events\Application\Query\ListEventAttendance;

use App\Events\Application\Contract\AttendanceReader;
use App\Events\Application\Contract\OccurrenceReader;
use App\Events\Application\Contract\RosterEntry;
use App\Events\Application\Contract\RosterPage;
use App\Events\Application\Contract\RosterRow;
use App\Events\Domain\AdmissionWindow;
use App\Events\Domain\EventException;
use App\Events\Domain\EventRules;
use App\IdentityAccess\Application\Contract\PermissionChecker;
use App\People\Application\Contract\PersonDirectory;
use Psr\Clock\ClockInterface;

final readonly class ListEventAttendanceHandler
{
    public function __construct(
        private PermissionChecker $authorization,
        private OccurrenceReader $occurrences,
        private AttendanceReader $attendance,
        private PersonDirectory $people,
        private ClockInterface $clock,
    ) {
    }

    public function handle(ListEventAttendanceQuery $query): RosterPage
    {
        $this->authorization->requirePermission('events.attendance.read');
        EventRules::page($query->limit, $query->offset);
        if (null !== $query->status && !\in_array($query->status, ['PRESENT', 'EXPECTED', 'ABSENT', 'WAITLISTED', 'CANCELLED'], true)) {
            throw EventException::invalid('Choose an attendance status.');
        }
        $search = EventRules::optional($query->search, 200);
        $occurrence = $this->occurrences->find($query->occurrenceId) ?? throw EventException::missing('Occurrence');
        $now = $this->clock->now();
        $matching = '' === trim($search) ? null : $this->people->matchingIds($search);

        $rows = $this->attendance->roster($occurrence->id, $matching, $query->status, $now, $query->limit, $query->offset);
        $names = [];
        foreach ($this->people->getAll(array_values(array_unique(array_map(static fn (RosterRow $r): string => $r->registration->personId, $rows)))) as $person) {
            $names[$person->id] = $person->name;
        }

        return new RosterPage(
            array_map(static fn (RosterRow $r): RosterEntry => new RosterEntry($r->registration, $r->attendance, $names[$r->registration->personId] ?? 'Unavailable person', $r->state), $rows),
            $this->attendance->rosterCount($occurrence->id, $matching, $query->status, $now),
            $this->attendance->counts($occurrence->id, $now),
            AdmissionWindow::isOpen($occurrence->status, $occurrence->startsAt, $occurrence->endsAt, $now),
        );
    }
}

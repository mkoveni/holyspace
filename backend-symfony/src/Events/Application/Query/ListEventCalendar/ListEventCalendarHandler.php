<?php

declare(strict_types=1);

namespace App\Events\Application\Query\ListEventCalendar;

use App\Events\Application\Contract\CalendarEntry;
use App\Events\Application\Contract\CalendarItem;
use App\Events\Application\Contract\CalendarPage;
use App\Events\Application\Contract\OccurrenceReader;
use App\Events\Application\Contract\RegistrationCounts;
use App\Events\Application\Contract\RegistrationReader;
use App\Events\Domain\EventException;
use App\Events\Domain\EventRules;
use App\IdentityAccess\Application\Contract\PermissionChecker;

final readonly class ListEventCalendarHandler
{
    public function __construct(
        private PermissionChecker $authorization,
        private OccurrenceReader $occurrences,
        private RegistrationReader $registrations,
    ) {
    }

    public function handle(ListEventCalendarQuery $query): CalendarPage
    {
        $this->authorization->requirePermission('events.read');
        EventRules::page($query->limit, $query->offset);
        EventRules::range($query->from, $query->until);
        $search = EventRules::optional($query->search, 200);
        if (null !== $query->status && !\in_array($query->status, ['SCHEDULED', 'CANCELLED'], true)) {
            throw EventException::invalid('Choose scheduled or cancelled.');
        }
        $page = $this->occurrences->calendar($query->eventId, $search, $query->from, $query->until, $query->status, $query->limit, $query->offset);
        $counts = $this->registrations->countsMany(array_map(static fn (CalendarEntry $e): string => $e->occurrence->id, $page));

        return new CalendarPage(
            array_map(static fn (CalendarEntry $e): CalendarItem => new CalendarItem($e->occurrence, $e->title, $e->type, $counts[$e->occurrence->id] ?? new RegistrationCounts(0, 0, 0)), $page),
            $this->occurrences->calendarCount($query->eventId, $search, $query->from, $query->until, $query->status),
        );
    }
}

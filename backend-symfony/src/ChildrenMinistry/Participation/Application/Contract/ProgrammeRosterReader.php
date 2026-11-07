<?php

declare(strict_types=1);

namespace App\ChildrenMinistry\Participation\Application\Contract;

use App\ChildrenMinistry\Participation\Application\Placement;
use App\ChildrenMinistry\Participation\Application\RosterPage;
use App\Shared\Domain\CalendarDate;

/** Enrolment roster of one programme, filtered by lifecycle status on a given day. */
interface ProgrammeRosterReader
{
    /**
     * @param list<string>|null $childIds restrict to these children; null for everyone, an empty list for nobody
     * @param string            $status   ALL, ACTIVE, UPCOMING, EXPIRED or ENDED
     */
    public function list(string $programmeId, ?array $childIds, string $status, CalendarDate $on, int $limit, int $offset): RosterPage;

    /**
     * @param list<string> $enrolmentIds
     *
     * @return array<string, list<Placement>> current class placements keyed by enrolment id
     */
    public function placements(array $enrolmentIds, CalendarDate $on): array;
}

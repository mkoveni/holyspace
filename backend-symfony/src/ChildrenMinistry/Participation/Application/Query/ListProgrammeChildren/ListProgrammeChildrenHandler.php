<?php

declare(strict_types=1);

namespace App\ChildrenMinistry\Participation\Application\Query\ListProgrammeChildren;

use App\ChildrenMinistry\Participation\Application\Contract\ParticipationReader;
use App\ChildrenMinistry\Participation\Application\Contract\ProgrammeRosterReader;
use App\ChildrenMinistry\Participation\Application\EnrolmentView;
use App\ChildrenMinistry\Participation\Application\ProgrammeChild;
use App\ChildrenMinistry\Participation\Application\ProgrammeChildrenPage;
use App\ChildrenMinistry\Safeguarding\Application\Contract\ChildReadiness;
use App\ChildrenMinistry\Shared\Domain\ChildrenException;
use App\ChildrenMinistry\Shared\Domain\ChildrenRules;
use App\IdentityAccess\Application\Contract\PermissionChecker;
use App\People\Application\Contract\PersonDirectory;
use App\Shared\Application\ChurchCalendar;
use App\Shared\Domain\CalendarDate;

final readonly class ListProgrammeChildrenHandler
{
    private const array STATUSES = ['ALL', 'ACTIVE', 'UPCOMING', 'EXPIRED', 'ENDED'];

    public function __construct(
        private PermissionChecker $authorization,
        private ParticipationReader $participation,
        private ProgrammeRosterReader $roster,
        private PersonDirectory $people,
        private ChildReadiness $readiness,
        private ChurchCalendar $calendar,
    ) {
    }

    public function handle(ListProgrammeChildrenQuery $query): ProgrammeChildrenPage
    {
        $this->authorization->requirePermission('children.read');
        ChildrenRules::page($query->limit, $query->offset);
        $search = ChildrenRules::optional($query->search, 120);
        $status = $query->status ?? 'ALL';
        if (!in_array($status, self::STATUSES, true)) {
            throw ChildrenException::invalid('Choose a valid enrolment status.');
        }
        if (null === $this->participation->programme($query->programmeId)) {
            throw ChildrenException::missing('Programme');
        }

        $on = $this->calendar->today();
        $childIds = '' === $search ? null : $this->people->matchingIds($search);
        $page = $this->roster->list($query->programmeId, $childIds, $status, $on, $query->limit, $query->offset);

        $people = [];
        foreach ($this->people->getAll(array_values(array_unique(array_map(static fn (EnrolmentView $enrolment): string => $enrolment->childId, $page->enrolments)))) as $person) {
            $people[$person->id] = $person;
        }
        $placements = $this->roster->placements(array_map(static fn (EnrolmentView $enrolment): string => $enrolment->id, $page->enrolments), $on);
        $current = array_values(array_unique(array_map(
            static fn (EnrolmentView $enrolment): string => $enrolment->childId,
            array_filter($page->enrolments, static fn (EnrolmentView $enrolment): bool => $enrolment->covers($on)),
        )));
        $checks = $this->readiness->summaries($query->programmeId, $current);

        return new ProgrammeChildrenPage(
            array_map(fn (EnrolmentView $enrolment): ProgrammeChild => new ProgrammeChild(
                $enrolment->id,
                $enrolment->childId,
                isset($people[$enrolment->childId]) ? $people[$enrolment->childId]->name : 'Unavailable person',
                $enrolment->validFrom,
                $enrolment->validUntil,
                self::status($enrolment, $on),
                $placements[$enrolment->id] ?? [],
                $enrolment->covers($on) ? ($checks[$enrolment->childId] ?? null) : null,
            ), $page->enrolments),
            $page->total,
        );
    }

    private static function status(EnrolmentView $enrolment, CalendarDate $on): string
    {
        if (null !== $enrolment->endedAt) {
            return 'ENDED';
        }
        if ($enrolment->validFrom->isAfter($on)) {
            return 'UPCOMING';
        }

        return $enrolment->validUntil->isAfter($on) ? 'ACTIVE' : 'EXPIRED';
    }
}

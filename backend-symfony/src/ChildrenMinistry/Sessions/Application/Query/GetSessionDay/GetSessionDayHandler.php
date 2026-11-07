<?php

declare(strict_types=1);

namespace App\ChildrenMinistry\Sessions\Application\Query\GetSessionDay;

use App\ChildrenMinistry\Sessions\Application\AttendanceView;
use App\ChildrenMinistry\Sessions\Application\Contract\SessionDayReader;
use App\ChildrenMinistry\Sessions\Application\Contract\SessionReader;
use App\ChildrenMinistry\Sessions\Application\SessionChildPreparation;
use App\ChildrenMinistry\Sessions\Application\SessionDayChild;
use App\ChildrenMinistry\Sessions\Application\SessionDayPage;
use App\ChildrenMinistry\Sessions\Application\SessionOperations;
use App\ChildrenMinistry\Sessions\Application\StaffScope;
use App\ChildrenMinistry\Sessions\Domain\Attendance;
use App\ChildrenMinistry\Shared\Domain\ChildrenException;
use App\ChildrenMinistry\Shared\Domain\ChildrenRules;
use App\IdentityAccess\Application\Contract\PermissionChecker;
use App\People\Application\Contract\PersonDirectory;
use App\People\Application\Contract\PersonDirectoryEntry;
use DateTimeImmutable;
use Psr\Clock\ClockInterface;

/**
 * The station's working list. Search runs over the whole selected view before paging. Returns no care or guardian
 * evidence, only names, attendance and what blocks arrival or collection.
 */
final readonly class GetSessionDayHandler
{
    public function __construct(
        private PermissionChecker $authorization,
        private StaffScope $scope,
        private SessionReader $sessions,
        private SessionDayReader $reader,
        private PersonDirectory $people,
        private SessionOperations $operations,
        private SessionChildPreparation $preparation,
        private ClockInterface $clock,
    ) {
    }

    public function handle(GetSessionDayQuery $query): SessionDayPage
    {
        $this->authorization->requireActorPermission($query->actorId, 'children.station');
        $this->scope->require($query->actorId, $query->sessionId);
        ChildrenRules::page($query->limit, $query->offset);
        if (!in_array($query->view, ['EXPECTED', 'ATTENDING', 'HISTORY'], true)) {
            throw ChildrenException::invalid('Choose a valid station view.');
        }
        $search = ChildrenRules::optional($query->search, 120);
        $matching = '' === $search ? null : $this->people->matchingIds($search);
        $session = $this->sessions->ministrySession($query->sessionId) ?? throw ChildrenException::missing('Session');
        $now = $this->clock->now();

        if ('EXPECTED' === $query->view) {
            $page = $this->reader->expected($session->classId, $this->operations->date($session), $matching, $query->limit, $query->offset);
            $names = $this->names($page['childIds']);
            $issues = $this->preparation->issues($session, $names, $now);
            $current = $this->reader->current($session->id, $page['childIds']);
            $children = [];
            foreach ($page['childIds'] as $id) {
                $found = $issues[$id] ?? [];
                $children[] = new SessionDayChild($id, isset($names[$id]) ? $names[$id]->name : 'Child profile unavailable', isset($current[$id]) ? AttendanceView::from($current[$id]) : null, [] === $found, $found);
            }

            return new SessionDayPage($children, $page['total'], $now);
        }

        $page = $this->reader->attendance($session->id, 'HISTORY' === $query->view, $matching, $query->limit, $query->offset);
        $names = $this->names(array_values(array_unique(array_map(static fn (Attendance $value): string => $value->childId, $page['attendance']))));

        return new SessionDayPage(
            array_map(fn (Attendance $value): SessionDayChild => new SessionDayChild($value->childId, isset($names[$value->childId]) ? $names[$value->childId]->name : 'Child profile unavailable', AttendanceView::from($value), true, $this->collectionIssues($value, $now)), $page['attendance']),
            $page['total'],
            $now,
        );
    }

    /** @return list<string> */
    private function collectionIssues(Attendance $value, DateTimeImmutable $now): array
    {
        if (null !== $value->checkedOutAt) {
            return [];
        }
        $issues = [];
        if (null === $value->labelHash) {
            $issues[] = 'Issue a pickup label before collection.';
        } elseif (null === $value->labelExpiresAt || $now >= $value->labelExpiresAt) {
            $issues[] = 'Replace the expired pickup label before collection.';
        }
        if ($value->failedAttempts > 0) {
            $issues[] = 'Review unsuccessful pickup-code attempts before handover.';
        }

        return $issues;
    }

    /**
     * @param list<string> $ids
     *
     * @return array<string, PersonDirectoryEntry>
     */
    private function names(array $ids): array
    {
        $names = [];
        foreach ($this->people->getAll($ids) as $person) {
            $names[$person->id] = $person;
        }

        return $names;
    }
}

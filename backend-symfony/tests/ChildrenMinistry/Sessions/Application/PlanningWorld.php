<?php

declare(strict_types=1);

namespace App\Tests\ChildrenMinistry\Sessions\Application;

use App\ChildrenMinistry\Sessions\Application\Contract\TeamPlanningReader;
use App\ChildrenMinistry\Sessions\Application\PlanningBooking;
use App\ChildrenMinistry\Sessions\Application\PlanningFacts;
use App\ChildrenMinistry\Sessions\Application\PlanningSession;
use App\ChildrenMinistry\Sessions\Application\PlanningWorkerPage;
use App\ChildrenMinistry\Sessions\Application\TeamPlanning;
use App\ChildrenMinistry\Sessions\Domain\MinistrySession;
use App\ChildrenMinistry\Sessions\Domain\Repository\TeamAssignmentRepository;
use App\ChildrenMinistry\Sessions\Domain\SessionStaff;
use App\ChildrenMinistry\Sessions\Domain\SessionStatus;
use App\ChildrenMinistry\Sessions\Domain\Worker;
use App\Shared\Application\ChurchCalendar;
use App\Tests\ChildrenMinistry\Shared\CallLog;
use DateTimeImmutable;
use Psr\Clock\ClockInterface;

/** The session fixture seen as the planning reader: facts are derived from its sessions, staff and workers. */
final class PlanningWorld implements TeamPlanningReader, TeamAssignmentRepository
{
    public function __construct(public readonly SessionFixture $world, public readonly CallLog $log)
    {
    }

    public function planning(ClockInterface $clock): TeamPlanning
    {
        return new TeamPlanning($this, new SeriesPeople($this->world), $this->world, $clock, new ChurchCalendar($clock, 'Africa/Johannesburg'));
    }

    /** Adds another planned session in its own class and room, so it can overlap the first. */
    public function addSession(string $id, string $start, string $end, SessionStatus $status = SessionStatus::Planned, int $seats = 10): MinistrySession
    {
        $session = MinistrySession::reconstitute($id, SessionFixture::CLASS_ID, SessionFixture::ROOM, new DateTimeImmutable($start), new DateTimeImmutable($end), $seats, $status, 0);
        $this->world->sessions[$id] = $session;

        return $session;
    }

    public function upcoming(DateTimeImmutable $from, DateTimeImmutable $until, ?string $programmeId, ?string $locationId, DateTimeImmutable $now): array
    {
        return array_values(array_map($this->row(...), array_filter(
            $this->world->sessions,
            static fn (MinistrySession $s): bool => $s->startsAt >= $from && $s->startsAt < $until && $s->startsAt > $now && !$s->status->terminal()
                && (null === $programmeId || SessionFixture::PROGRAMME === $programmeId) && (null === $locationId || SessionFixture::LOCATION === $locationId),
        )));
    }

    public function sessions(array $ids): array
    {
        return array_values(array_map($this->row(...), array_filter($this->world->sessions, static fn (MinistrySession $s): bool => \in_array($s->id, $ids, true))));
    }

    public function facts(array $sessions, array $extraWorkers): PlanningFacts
    {
        $ids = array_map(static fn (PlanningSession $row): string => $row->value->id, $sessions);
        $staff = array_values(array_filter($this->world->staff, static fn (SessionStaff $member): bool => \in_array($member->sessionId, $ids, true)));
        $workerIds = array_unique([...$extraWorkers, ...array_map(static fn (SessionStaff $member): string => $member->workerId, $staff)]);
        $workers = array_values(array_filter($this->world->workers, static fn (Worker $worker): bool => \in_array($worker->personId, $workerIds, true)));
        $bookings = [];
        foreach ($this->world->staff as $member) {
            $session = $this->world->sessions[$member->sessionId] ?? null;
            if (null !== $session && !$session->status->terminal() && \in_array($member->workerId, $workerIds, true)) {
                $bookings[] = new PlanningBooking($session->id, $member->workerId, $session->startsAt, $session->endsAt);
            }
        }

        return new PlanningFacts($staff, $workers, $bookings);
    }

    public function workers(?array $matchingIds, int $limit, int $offset): PlanningWorkerPage
    {
        $all = array_values(array_filter($this->world->workers, static fn (Worker $worker): bool => null === $matchingIds || \in_array($worker->personId, $matchingIds, true)));

        return new PlanningWorkerPage(\array_slice($all, $offset, $limit), \count($all));
    }

    public function removeAssignment(string $sessionId, string $workerId): void
    {
        $this->log->entries[] = 'remove:'.$workerId;
        $this->world->staff = array_values(array_filter($this->world->staff, static fn (SessionStaff $member): bool => !($member->sessionId === $sessionId && $member->workerId === $workerId)));
    }

    private function row(MinistrySession $session): PlanningSession
    {
        return new PlanningSession($session, SessionFixture::PROGRAMME, 'Sunday', 'Juniors', 'Room 1', SessionFixture::LOCATION, 'Africa/Johannesburg', $this->world->roomCapacity, $this->world->minStaff, $this->world->childrenPerStaff);
    }
}

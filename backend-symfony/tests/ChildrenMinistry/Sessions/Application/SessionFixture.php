<?php

declare(strict_types=1);

namespace App\Tests\ChildrenMinistry\Sessions\Application;

use App\ChildrenMinistry\Participation\Application\AssignmentView;
use App\ChildrenMinistry\Participation\Application\ClassView;
use App\ChildrenMinistry\Participation\Application\Contract\ParticipationReader;
use App\ChildrenMinistry\Participation\Application\Contract\VenueReader;
use App\ChildrenMinistry\Participation\Application\EnrolmentView;
use App\ChildrenMinistry\Participation\Application\LocationView;
use App\ChildrenMinistry\Participation\Application\ProgrammeView;
use App\ChildrenMinistry\Participation\Application\RoomView;
use App\ChildrenMinistry\Sessions\Application\Contract\AttendanceReader;
use App\ChildrenMinistry\Sessions\Application\Contract\SessionIncidents;
use App\ChildrenMinistry\Sessions\Application\Contract\SessionReader;
use App\ChildrenMinistry\Sessions\Application\Contract\VolunteerStaffLink;
use App\ChildrenMinistry\Sessions\Application\Contract\VolunteerStaffLinks;
use App\ChildrenMinistry\Sessions\Application\Contract\VolunteerStaffLinkWriter;
use App\ChildrenMinistry\Sessions\Application\IncidentCounts;
use App\ChildrenMinistry\Sessions\Application\SessionOperations;
use App\ChildrenMinistry\Sessions\Domain\Attendance;
use App\ChildrenMinistry\Sessions\Domain\AttendanceEvent;
use App\ChildrenMinistry\Sessions\Domain\AttendanceSegment;
use App\ChildrenMinistry\Sessions\Domain\MinistrySession;
use App\ChildrenMinistry\Sessions\Domain\Repository\AttendanceRepository;
use App\ChildrenMinistry\Sessions\Domain\Repository\SessionRepository;
use App\ChildrenMinistry\Sessions\Domain\SessionStaff;
use App\ChildrenMinistry\Sessions\Domain\SessionStatus;
use App\ChildrenMinistry\Sessions\Domain\StaffRole;
use App\ChildrenMinistry\Sessions\Domain\Worker;
use App\IdentityAccess\Application\Contract\AccountDirectory;
use App\Shared\Domain\CalendarDate;
use App\Tests\ChildrenMinistry\Shared\CallLog;
use App\Volunteering\Application\Contract\StaffingAssessment;
use App\Volunteering\Application\Contract\StaffingDirectory;
use App\Volunteering\Application\Contract\StaffingUnavailable;
use DateTimeImmutable;

/**
 * One in-memory world for session handler tests: a Johannesburg room of 12, a class in a programme needing one
 * adult per two children, and a session on 2026-06-07 09:00-11:00 UTC (11:00-13:00 local). Every touch of the
 * session row, attendance and incidents is written to the call log.
 */
final class SessionFixture implements SessionRepository, SessionReader, VenueReader, ParticipationReader, AttendanceReader, SessionIncidents, VolunteerStaffLinks, VolunteerStaffLinkWriter, StaffingDirectory, AccountDirectory, AttendanceRepository
{
    public const string CLASS_ID = 'c0000000-0000-4000-8000-000000000001';
    public const string PROGRAMME = 'a0000000-0000-4000-8000-000000000001';
    public const string LOCATION = 'b0000000-0000-4000-8000-000000000001';
    public const string ROOM = 'd0000000-0000-4000-8000-000000000001';
    public const string SESSION = 'e0000000-0000-4000-8000-000000000001';

    /** @var array<string, MinistrySession> */
    public array $sessions = [];
    /** @var list<SessionStaff> */
    public array $staff = [];
    /** @var array<string, Worker> */
    public array $workers = [];
    /** @var array<string, array{string, ?string}> person id => [name, birth date] */
    public array $people = [];
    /** @var array<string, bool> account id => active */
    public array $accounts = [];
    /** @var array<string, VolunteerStaffLink> */
    public array $links = [];
    /** @var array<string, list<string>|null> assignment id => issues, null when unavailable */
    public array $assessments = [];
    /** @var array<string, string> assignment id => person id */
    public array $assignmentPerson = [];
    public int $occupancy = 0;
    public int $unresolved = 0;
    public int $minStaff = 1;
    public int $childrenPerStaff = 2;
    public int $roomCapacity = 12;
    public ?string $policyId = 'f0000000-0000-4000-8000-000000000001';

    public function __construct(public readonly CallLog $log, SessionStatus $status = SessionStatus::Planned, int $seats = 10)
    {
        $this->sessions[self::SESSION] = MinistrySession::reconstitute(self::SESSION, self::CLASS_ID, self::ROOM, new DateTimeImmutable('2026-06-07T09:00:00Z'), new DateTimeImmutable('2026-06-07T11:00:00Z'), $seats, $status, 0);
    }

    public function operations(): SessionOperations
    {
        return new SessionOperations($this, $this, $this, $this, new FixturePeople($this), $this, $this, $this);
    }

    /** Adds a cleared, present adult worker whose account is active. */
    public function serve(string $id, StaffRole $role = StaffRole::Assistant, bool $present = true, string $birth = '1990-01-01', string $screenedUntil = '2027-01-01T00:00:00Z'): void
    {
        $this->people[$id] = ['Worker '.substr($id, 0, 2), $birth];
        $this->accounts['acct-'.$id] = true;
        $this->workers[$id] = Worker::reconstitute($id, 'acct-'.$id, new DateTimeImmutable('2026-01-01T00:00:00Z'), new DateTimeImmutable($screenedUntil), new DateTimeImmutable($screenedUntil), 'Checked', 'v', null, 0);
        $this->staff[] = new SessionStaff(self::SESSION, $id, $role, $present);
    }

    public function ministrySession(string $id): ?MinistrySession
    {
        return $this->sessions[$id] ?? null;
    }

    public function ministrySessionForUpdate(string $id): ?MinistrySession
    {
        $this->log->entries[] = 'forUpdate:session';

        return $this->sessions[$id] ?? null;
    }

    public function insertMinistrySession(MinistrySession $session): void
    {
        $this->log->entries[] = 'insert:session';
        $this->sessions[$session->id] = $session;
    }

    public function updateMinistrySession(MinistrySession $session): void
    {
        $this->log->entries[] = 'update:session:'.$session->status->value;
        $this->sessions[$session->id] = $session;
    }

    /** @var array<string, bool> worker id => present in another overlapping session */
    public array $elsewhere = [];

    public function presentElsewhere(string $workerId, string $sessionId, DateTimeImmutable $from, DateTimeImmutable $until): bool
    {
        $this->log->entries[] = 'read:elsewhere';

        return $this->elsewhere[$workerId] ?? false;
    }

    public function workerForUpdate(string $personId): ?Worker
    {
        $this->log->entries[] = 'forUpdate:worker';

        return $this->workers[$personId] ?? null;
    }

    public function insertWorker(Worker $worker): void
    {
        $this->log->entries[] = 'insert:worker';
        $this->workers[$worker->personId] = $worker;
    }

    public function updateWorker(Worker $worker): void
    {
        $this->log->entries[] = 'update:worker';
        $this->workers[$worker->personId] = $worker;
    }

    public function saveSessionStaff(SessionStaff $staff): void
    {
        $this->log->entries[] = 'save:staff:'.$staff->role->value.':'.($staff->present ? 'present' : 'absent');
        $this->staff = array_values(array_filter($this->staff, static fn (SessionStaff $value): bool => $value->workerId !== $staff->workerId));
        $this->staff[] = $staff;
    }

    public function sessions(?string $locationId, DateTimeImmutable $from, DateTimeImmutable $until, ?int $limit, int $offset): array
    {
        return array_values($this->sessions);
    }

    public function stationSessions(string $accountId, DateTimeImmutable $from, DateTimeImmutable $until, int $limit, int $offset): array
    {
        return array_values($this->sessions);
    }

    public function staff(string $sessionId): array
    {
        return array_values(array_filter($this->staff, static fn (SessionStaff $staff): bool => $staff->sessionId === $sessionId));
    }

    public function worker(string $personId): ?Worker
    {
        return $this->workers[$personId] ?? null;
    }

    public function workerByAccount(string $accountId): ?Worker
    {
        foreach ($this->workers as $worker) {
            if ($worker->accountId === $accountId) {
                return $worker;
            }
        }

        return null;
    }

    public function location(string $id): ?LocationView
    {
        return self::LOCATION === $id ? new LocationView($id, 'Main', 'Africa/Johannesburg') : null;
    }

    public function locations(int $limit, int $offset): array
    {
        return [new LocationView(self::LOCATION, 'Main', 'Africa/Johannesburg')];
    }

    public function room(string $id): ?RoomView
    {
        return self::ROOM === $id ? new RoomView($id, self::LOCATION, 'Room 1', $this->roomCapacity) : null;
    }

    public function rooms(string $locationId): array
    {
        return [new RoomView(self::ROOM, self::LOCATION, 'Room 1', $this->roomCapacity)];
    }

    public function programme(string $id): ?ProgrammeView
    {
        return self::PROGRAMME === $id ? new ProgrammeView($id, 'Sunday', $this->policyId, $this->minStaff, $this->childrenPerStaff, 0, new DateTimeImmutable('2026-01-01T00:00:00Z')) : null;
    }

    public function programmes(string $search, int $limit, int $offset): array
    {
        return [];
    }

    public function ministryClass(string $id): ?ClassView
    {
        return self::CLASS_ID === $id ? new ClassView($id, self::PROGRAMME, 'Juniors', 60, 120) : null;
    }

    public function enrolment(string $id): ?EnrolmentView
    {
        return null;
    }

    public function classAssignment(string $id): ?AssignmentView
    {
        return null;
    }

    public function enrolments(string $childId, int $limit, int $offset): array
    {
        return [];
    }

    public function assignments(string $childId, int $limit, int $offset): array
    {
        return [];
    }

    public function classes(string $programmeId): array
    {
        return [];
    }

    /** @var array<string, Attendance> */
    public array $attendances = [];
    /** @var list<AttendanceSegment> */
    public array $segments = [];
    /** @var list<AttendanceEvent> */
    public array $events = [];
    /** @var array<string, EnrolmentView> programme id . child => enrolment */
    public array $enrolments = [];
    /** @var array<string, AssignmentView> class id . child => placement */
    public array $placements = [];

    public function occupancy(string $sessionId): int
    {
        $this->log->entries[] = 'read:occupancy';

        return $this->occupancy + count(array_filter($this->attendances, static fn (Attendance $a): bool => $a->sessionId === $sessionId && null === $a->checkedOutAt));
    }

    public function attendance(string $id): ?Attendance
    {
        $this->log->entries[] = 'read:attendance';

        return $this->attendances[$id] ?? null;
    }

    public function attendanceForUpdate(string $id): ?Attendance
    {
        $this->log->entries[] = 'forUpdate:attendance';

        return $this->attendances[$id] ?? null;
    }

    public function insertAttendance(Attendance $attendance): void
    {
        $this->log->entries[] = 'insert:attendance';
        $this->attendances[$attendance->id] = $attendance;
    }

    public function updateAttendance(Attendance $attendance): void
    {
        $this->log->entries[] = 'update:attendance';
        $this->attendances[$attendance->id] = $attendance;
    }

    public function insertAttendanceSegment(AttendanceSegment $segment): void
    {
        $this->log->entries[] = 'insert:segment';
        $this->segments[] = $segment;
    }

    public function insertAttendanceEvent(AttendanceEvent $event): void
    {
        $this->log->entries[] = 'insert:event:'.$event->type;
        $this->events[] = $event;
    }

    public function closeSegment(string $attendanceId, DateTimeImmutable $at): void
    {
        $this->log->entries[] = 'close:segment';
    }

    public function childAttendance(string $childId, int $limit, int $offset): array
    {
        return array_slice(array_values(array_filter($this->attendances, static fn (Attendance $a): bool => $a->childId === $childId)), $offset, $limit);
    }

    public function roster(string $sessionId, bool $openOnly, int $limit, int $offset): array
    {
        return array_values(array_filter($this->attendances, static fn (Attendance $a): bool => $a->sessionId === $sessionId && (!$openOnly || null === $a->checkedOutAt)));
    }

    public function checkedIn(string $childId, string $sessionId): bool
    {
        foreach ($this->attendances as $a) {
            if ($a->childId === $childId && $a->sessionId === $sessionId && null === $a->checkedOutAt) {
                return true;
            }
        }

        return false;
    }

    public function arrival(string $actorId, string $requestId): ?Attendance
    {
        $this->log->entries[] = 'read:arrival';
        foreach ($this->attendances as $a) {
            if ($a->arrivedBy === $actorId && $a->arrivalRequestId === $requestId) {
                return $a;
            }
        }

        return null;
    }

    public function events(string $attendanceId, int $limit, int $offset): array
    {
        return $this->events;
    }

    public function segments(string $attendanceId): array
    {
        return array_values(array_filter($this->segments, static fn (AttendanceSegment $s): bool => $s->attendanceId === $attendanceId));
    }

    public function counts(string $sessionId, CalendarDate $on): IncidentCounts
    {
        return new IncidentCounts($this->unresolved, 0, 0);
    }

    public function enrolmentAt(string $programmeId, string $childId, CalendarDate $on): ?EnrolmentView
    {
        $this->log->entries[] = 'read:enrolmentAt';

        return $this->enrolments[$programmeId.$childId] ?? null;
    }

    public function assignmentAt(string $classId, string $childId, CalendarDate $on): ?AssignmentView
    {
        $this->log->entries[] = 'read:assignmentAt';

        return $this->placements[$classId.$childId] ?? null;
    }

    public function expectedChildren(string $classId, CalendarDate $on, int $limit, int $offset): array
    {
        return array_values($this->placements);
    }

    public function unresolved(string $sessionId, CalendarDate $on): int
    {
        $this->log->entries[] = 'read:incidents:'.$on->iso();

        return $this->unresolved;
    }

    public function find(string $sessionId, string $workerId): ?VolunteerStaffLink
    {
        return $this->links[$workerId] ?? null;
    }

    public function assess(string $assignmentId, string $sessionId, string $responsibility, DateTimeImmutable $from, DateTimeImmutable $until): StaffingAssessment
    {
        $this->log->entries[] = 'assess';
        $issues = $this->assessments[$assignmentId] ?? throw new StaffingUnavailable();

        return new StaffingAssessment($assignmentId, $this->assignmentPerson[$assignmentId] ?? 'p', 'n', $responsibility, [] === $issues, $issues);
    }

    /** @var list<StaffingAssessment>|null null makes the directory unavailable */
    public ?array $candidates = [];

    public function candidates(string $sessionId, DateTimeImmutable $from, DateTimeImmutable $until): array
    {
        return $this->candidates ?? throw new StaffingUnavailable();
    }

    public function save(string $sessionId, string $workerId, string $assignmentId, string $role): void
    {
        $this->log->entries[] = 'link:'.$role;
        $this->links[$workerId] = new VolunteerStaffLink($assignmentId, $role);
    }

    public function active(string $accountId): bool
    {
        return $this->accounts[$accountId] ?? false;
    }
}

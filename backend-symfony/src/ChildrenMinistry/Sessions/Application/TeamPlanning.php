<?php

declare(strict_types=1);

namespace App\ChildrenMinistry\Sessions\Application;

use App\ChildrenMinistry\Sessions\Application\Contract\PlanningSessionView;
use App\ChildrenMinistry\Sessions\Application\Contract\PlanningStaffView;
use App\ChildrenMinistry\Sessions\Application\Contract\PlanningSummary;
use App\ChildrenMinistry\Sessions\Application\Contract\TeamPlanningAttention;
use App\ChildrenMinistry\Sessions\Application\Contract\TeamPlanningReader;
use App\ChildrenMinistry\Sessions\Application\Contract\TeamPlanningView;
use App\ChildrenMinistry\Sessions\Domain\CapacityPolicy;
use App\ChildrenMinistry\Sessions\Domain\SessionStaff;
use App\ChildrenMinistry\Sessions\Domain\SessionStatus;
use App\ChildrenMinistry\Sessions\Domain\StaffRole;
use App\ChildrenMinistry\Sessions\Domain\Worker;
use App\ChildrenMinistry\Shared\Domain\ChildrenException;
use App\ChildrenMinistry\Shared\Domain\ChildrenRules;
use App\IdentityAccess\Application\Contract\AccountDirectory;
use App\People\Application\Contract\PersonDirectory;
use App\People\Application\Contract\PersonDirectoryEntry;
use App\Shared\Application\ChurchCalendar;
use App\Shared\Domain\CalendarDate;
use DateTimeImmutable;
use DateTimeZone;
use Psr\Clock\ClockInterface;

/**
 * Readiness of upcoming teams and what a bulk change would do to it. Expected capacity ignores presence and leaves out
 * unqualified or double-booked staff; clearance warnings allow planning, clashes block saving.
 */
final readonly class TeamPlanning implements TeamPlanningAttention
{
    private const int MAX_SESSIONS = 1000;
    private const int MAX_TARGETS = 25;

    public function __construct(
        private TeamPlanningReader $reader,
        private PersonDirectory $people,
        private AccountDirectory $accounts,
        private ClockInterface $clock,
        private ChurchCalendar $calendar,
    ) {
    }

    public function attention(CalendarDate $from, CalendarDate $until, int $limit): TeamPlanningView
    {
        return $this->list($from, $until, null, null, '', 'ATTENTION', $limit, 0);
    }

    public function list(CalendarDate $from, CalendarDate $until, ?string $programmeId, ?string $locationId, string $search, string $state, int $limit, int $offset): TeamPlanningView
    {
        ChildrenRules::page($limit, $offset);
        $days = (new DateTimeImmutable($from->iso(), new DateTimeZone('UTC')))->diff(new DateTimeImmutable($until->iso(), new DateTimeZone('UTC')));
        if ($until->isBefore($from) || $days->days > 89) {
            throw ChildrenException::invalid('Choose up to 90 days for team planning.');
        }
        if (!\in_array($state, ['ALL', 'READY', 'ATTENTION'], true)) {
            throw ChildrenException::invalid('Choose a valid readiness view.');
        }
        $now = $this->clock->now();
        $zone = new DateTimeZone($this->calendar->timeZone());
        $selected = $this->reader->upcoming(new DateTimeImmutable($from->iso().' 00:00:00', $zone), new DateTimeImmutable($until->plusDays(1)->iso().' 00:00:00', $zone), $programmeId, $locationId, $now);
        if (\count($selected) > self::MAX_SESSIONS) {
            throw ChildrenException::invalid('More than 1,000 sessions match. Narrow the dates or programme.');
        }

        $term = mb_strtolower(ChildrenRules::optional($search, 120));
        $all = array_values(array_filter(
            $this->assess($selected, $this->reader->facts($selected, []), null, $now),
            static fn (PlanningSessionView $row): bool => str_contains(mb_strtolower($row->programme.' '.$row->ministryClass.' '.$row->room.' '.implode(' ', array_map(static fn (PlanningStaffView $member): string => $member->name, $row->team))), $term),
        ));
        $filtered = array_values(array_filter($all, static fn (PlanningSessionView $row): bool => 'ALL' === $state || ('READY' === $state) === $row->ready));
        $count = static fn (callable $test): int => \count(array_filter($all, $test));
        $summary = new PlanningSummary(
            \count($all),
            $count(static fn (PlanningSessionView $row): bool => $row->ready),
            $count(static fn (PlanningSessionView $row): bool => !$row->ready),
            $count(static fn (PlanningSessionView $row): bool => !$row->qualifiedLeader),
            $count(static fn (PlanningSessionView $row): bool => $row->plannedCapacity < $row->session->seatLimit),
            $count(static fn (PlanningSessionView $row): bool => [] !== array_filter($row->team, static fn (PlanningStaffView $member): bool => $member->clash)),
        );

        return new TeamPlanningView(\array_slice($filtered, $offset, $limit), \count($filtered), $summary, $this->calendar->timeZone(), $now);
    }

    public function workers(string $search, int $limit, int $offset): PlanningWorkerList
    {
        ChildrenRules::page($limit, $offset);
        $term = ChildrenRules::optional($search, 120);
        $page = $this->reader->workers('' === $term ? null : $this->people->matchingIds($term), $limit, $offset);
        $names = $this->names(array_map(static fn (Worker $worker): string => $worker->personId, $page->values));

        return new PlanningWorkerList(array_map(static fn (Worker $worker): PlanningWorkerOption => new PlanningWorkerOption(
            $worker->personId,
            isset($names[$worker->personId]) ? $names[$worker->personId]->name : 'Unavailable person',
            $worker->screenedUntil,
            $worker->trainedUntil,
            null !== $worker->revokedAt,
        ), $page->values), $page->total);
    }

    public function preview(TeamPlanDraft $draft): TeamPlanPreview
    {
        $this->validate($draft);
        $now = $this->clock->now();
        $selected = $this->reader->sessions(array_map(static fn (TeamPlanTarget $target): string => $target->sessionId, $draft->targets));
        if (\count($selected) !== \count($draft->targets)) {
            throw ChildrenException::missing('Session');
        }
        $facts = $this->reader->facts($selected, [$draft->workerId]);
        if ([] === array_filter($facts->workers, static fn (Worker $worker): bool => $worker->personId === $draft->workerId)) {
            throw ChildrenException::missing('Worker');
        }

        $versions = [];
        foreach ($draft->targets as $target) {
            $versions[$target->sessionId] = $target->version;
        }
        $blockers = [];
        foreach ($selected as $row) {
            $session = $row->value;
            if ($session->version !== $versions[$session->id]) {
                $blockers[] = 'A selected session changed. Reload the planning workspace.';
            }
            if (SessionStatus::Planned !== $session->status || $session->startsAt <= $now) {
                $blockers[] = 'Only future planned sessions can be changed in bulk.';
            }
            $team = array_values(array_filter($facts->staff, static fn (SessionStaff $member): bool => $member->sessionId === $session->id));
            if (null !== $draft->replaceWorkerId && [] === array_filter($team, static fn (SessionStaff $member): bool => $member->workerId === $draft->replaceWorkerId)) {
                $blockers[] = 'The person to replace is not assigned to every selected session.';
            }
            if ([] !== array_filter($team, static fn (SessionStaff $member): bool => $member->present && ($member->workerId === $draft->workerId || $member->workerId === $draft->replaceWorkerId))) {
                $blockers[] = 'Confirmed presence must be managed on the individual session.';
            }
        }
        $projected = $this->assess($selected, $facts, $draft, $now);
        foreach ($projected as $row) {
            if ([] !== array_filter($row->team, static fn (PlanningStaffView $member): bool => $member->workerId === $draft->workerId && $member->clash)) {
                $blockers[] = 'The selected worker has an overlapping assignment. Choose different sessions or resolve the clash first.';
                break;
            }
        }

        return new TeamPlanPreview($projected, array_values(array_unique($blockers)), $now);
    }

    public function validate(TeamPlanDraft $draft): void
    {
        $ids = array_map(static fn (TeamPlanTarget $target): string => $target->sessionId, $draft->targets);
        if ([] === $draft->targets || \count($draft->targets) > self::MAX_TARGETS || [] !== array_filter($draft->targets, static fn (TeamPlanTarget $target): bool => $target->version < 0)
            || \count(array_unique($ids)) !== \count($ids) || $draft->workerId === $draft->replaceWorkerId) {
            throw ChildrenException::invalid('Choose 1–25 distinct sessions, a registered worker, a role and a different person to replace.');
        }
    }

    /**
     * @param list<PlanningSession> $selected
     *
     * @return list<PlanningSessionView>
     */
    private function assess(array $selected, PlanningFacts $facts, ?TeamPlanDraft $draft, DateTimeImmutable $now): array
    {
        /** @var array<string, list<SessionStaff>> $team */
        $team = [];
        foreach ($facts->staff as $member) {
            $team[$member->sessionId][] = $member;
        }
        $ids = [];
        foreach ($selected as $row) {
            $ids[$row->value->id] = true;
            if (null !== $draft) {
                $changed = array_values(array_filter($team[$row->value->id] ?? [], static fn (SessionStaff $member): bool => $member->workerId !== $draft->workerId && $member->workerId !== $draft->replaceWorkerId));
                $changed[] = new SessionStaff($row->value->id, $draft->workerId, $draft->role, false);
                $team[$row->value->id] = $changed;
            }
        }
        $bookings = array_values(array_filter($facts->bookings, static fn (PlanningBooking $booking): bool => !isset($ids[$booking->sessionId])));
        foreach ($selected as $row) {
            foreach ($team[$row->value->id] ?? [] as $member) {
                $bookings[] = new PlanningBooking($row->value->id, $member->workerId, $row->value->startsAt, $row->value->endsAt);
            }
        }
        $busy = [];
        foreach ($bookings as $booking) {
            $busy[$booking->workerId][] = $booking;
        }
        $workers = [];
        $active = [];
        foreach ($facts->workers as $worker) {
            $workers[$worker->personId] = $worker;
            $active[$worker->personId] = $this->accounts->active($worker->accountId);
        }
        $names = $this->names(array_keys($workers));
        $zoneOf = static fn (string $zone): DateTimeZone => new DateTimeZone($zone);

        $views = [];
        foreach ($selected as $row) {
            $session = $row->value;
            $on = CalendarDate::fromInstant($session->startsAt, $zoneOf($row->timeZone));
            $staff = [];
            foreach ($team[$session->id] ?? [] as $member) {
                $worker = $workers[$member->workerId] ?? null;
                $person = $names[$member->workerId] ?? null;
                $issues = [];
                if (null === $person || !$person->available || null === $person->birthDate || $person->birthDate->plusYears(18)->isAfter($on)) {
                    $issues[] = "Review the person's availability and adult identity.";
                }
                if (null === $worker || !($active[$member->workerId] ?? false)) {
                    $issues[] = 'The linked worker account is unavailable.';
                }
                if (null !== $worker && !$worker->eligible($now, $session->endsAt)) {
                    $issues[] = 'Screening or training does not cover this session, or clearance is revoked.';
                }
                $qualified = [] === $issues;
                $clash = [] !== array_filter($busy[$member->workerId] ?? [], static fn (PlanningBooking $booking): bool => $booking->sessionId !== $session->id && $booking->startsAt < $session->endsAt && $booking->endsAt > $session->startsAt);
                if ($clash) {
                    $issues[] = 'Assigned to an overlapping session.';
                }
                $staff[] = new PlanningStaffView($member->workerId, null === $person ? 'Unavailable person' : $person->name, $member->role->value, $member->present, $qualified, $clash, $worker?->screenedUntil, $worker?->trainedUntil, $issues);
            }
            $usable = array_filter($staff, static fn (PlanningStaffView $member): bool => $member->qualified && !$member->clash);
            $leader = [] !== array_filter($usable, static fn (PlanningStaffView $member): bool => StaffRole::Leader->value === $member->role);
            $capacity = CapacityPolicy::capacity($session->seatLimit, $row->roomCapacity, \count($usable), $leader, $row->minimumStaff, $row->childrenPerStaff);
            $issues = [];
            if (!$leader) {
                $issues[] = 'A qualified, available leader is needed.';
            }
            if ($capacity < $session->seatLimit) {
                $issues[] = \sprintf('The planned team supports %d of %d seats.', $capacity, $session->seatLimit);
            }
            foreach ($staff as $member) {
                foreach ($member->issues as $issue) {
                    $issues[] = $member->name.': '.$issue;
                }
            }
            $views[] = new PlanningSessionView(
                $session,
                $row->programmeId,
                $row->programme,
                $row->ministryClass,
                $row->room,
                $row->timeZone,
                max($row->minimumStaff, intdiv($session->seatLimit + $row->childrenPerStaff - 1, $row->childrenPerStaff)),
                \count($usable),
                $leader,
                $capacity,
                [] === $issues,
                SessionStatus::Planned === $session->status && $session->startsAt > $now,
                $issues,
                $staff,
            );
        }

        return $views;
    }

    /**
     * @param list<string> $ids
     *
     * @return array<string, PersonDirectoryEntry>
     */
    private function names(array $ids): array
    {
        $names = [];
        foreach ($this->people->getAll($ids) as $entry) {
            $names[$entry->id] = $entry;
        }

        return $names;
    }
}

<?php

declare(strict_types=1);

namespace App\ChildrenMinistry\Sessions\Application;

use App\ChildrenMinistry\Participation\Application\Contract\ParticipationReader;
use App\ChildrenMinistry\Participation\Application\Contract\VenueReader;
use App\ChildrenMinistry\Sessions\Application\Contract\SessionReader;
use App\ChildrenMinistry\Sessions\Application\Contract\SessionSeriesReader;
use App\ChildrenMinistry\Sessions\Domain\CapacityPolicy;
use App\ChildrenMinistry\Sessions\Domain\StaffRole;
use App\ChildrenMinistry\Shared\Domain\ChildrenException;
use App\ChildrenMinistry\Shared\Domain\ChildrenRules;
use App\IdentityAccess\Application\Contract\AccountDirectory;
use App\People\Application\Contract\PersonDirectory;
use App\People\Application\Contract\PersonDirectoryEntry;
use App\Shared\Domain\CalendarDate;
use DateTimeZone;
use Psr\Clock\ClockInterface;

/** Previews a series without writing: dates, clashes and what the copied team would look like on each. */
final readonly class SessionSeriesPlanning
{
    public function __construct(
        private SessionReader $sessions,
        private SessionSeriesReader $series,
        private ParticipationReader $participation,
        private VenueReader $venues,
        private PersonDirectory $people,
        private AccountDirectory $accounts,
        private ClockInterface $clock,
    ) {
    }

    public function preview(SessionSeriesDraft $draft): SeriesPreview
    {
        ChildrenRules::text($draft->name, 120);
        $source = $this->sessions->ministrySession($draft->sourceSessionId) ?? throw ChildrenException::missing('Source session');
        ChildrenRules::version($source->version, $draft->sourceVersion);
        $room = $this->venues->room($source->roomId) ?? throw ChildrenException::missing('Room');
        $location = $this->venues->location($room->locationId) ?? throw ChildrenException::missing('Location');
        $group = $this->participation->ministryClass($source->classId) ?? throw ChildrenException::missing('Class');
        $programme = $this->participation->programme($group->programmeId) ?? throw ChildrenException::missing('Programme');
        if ($source->seatLimit > $room->capacity) {
            throw ChildrenException::invalid('Session seats exceed room capacity.');
        }

        $team = $this->sessions->staff($source->id);
        $names = [];
        foreach ($this->people->getAll(array_map(static fn ($member): string => $member->workerId, $team)) as $entry) {
            $names[$entry->id] = $entry;
        }
        $workers = [];
        $active = [];
        foreach ($team as $member) {
            $worker = $this->sessions->worker($member->workerId) ?? throw ChildrenException::missing('Worker');
            $workers[$worker->personId] = $worker;
            $active[$worker->personId] = $this->accounts->active($worker->accountId);
        }

        $now = $this->clock->now();
        $occurrences = [];
        foreach ($draft->recurrence->slots(new DateTimeZone($location->timeZone)) as $slot) {
            $conflicts = [];
            if (!$slot->excluded) {
                if ($slot->startsAt <= $now) {
                    $conflicts[] = 'Choose a future start time.';
                }
                if ($this->series->conflicts($source->classId, $source->roomId, $slot->startsAt, $slot->endsAt)) {
                    $conflicts[] = 'The class or room is already booked for this time.';
                }
            }
            $warnings = [];
            $eligible = 0;
            $leader = false;
            foreach ($team as $member) {
                $person = $names[$member->workerId] ?? null;
                if (!$workers[$member->workerId]->eligible($now, $slot->endsAt) || !$active[$member->workerId] || !$this->adultAndAvailable($person, $slot->date)) {
                    $warnings[] = (null === $person ? 'Team member' : $person->name).': review identity, account or screening/training before this session.';
                } else {
                    ++$eligible;
                    $leader = $leader || StaffRole::Leader === $member->role;
                }
            }
            $capacity = CapacityPolicy::capacity($source->seatLimit, $room->capacity, $eligible, $leader, $programme->minStaff, $programme->childrenPerStaff);
            if ($capacity < $source->seatLimit) {
                $warnings[] = \sprintf('The copied team currently supports up to %d of %d planned seats once present. Review staffing before opening.', $capacity, $source->seatLimit);
            }
            if ([] === array_filter($team, static fn ($member): bool => StaffRole::Leader === $member->role)) {
                $warnings[] = 'Assign a leader before opening this session.';
            }
            $occurrences[] = new PreviewOccurrence($slot, $conflicts, $warnings);
        }

        return new SeriesPreview(
            $group->name,
            $room->name,
            $location->timeZone,
            $source->seatLimit,
            array_map(static fn ($member): PreviewTeamMember => new PreviewTeamMember($member->workerId, isset($names[$member->workerId]) ? $names[$member->workerId]->name : 'Team member', $member->role), $team),
            $occurrences,
            $now,
        );
    }

    private function adultAndAvailable(?PersonDirectoryEntry $person, CalendarDate $on): bool
    {
        return null !== $person && $person->available && null !== $person->birthDate && !$person->birthDate->plusYears(18)->isAfter($on);
    }
}

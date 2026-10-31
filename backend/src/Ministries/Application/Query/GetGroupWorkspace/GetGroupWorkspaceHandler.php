<?php

declare(strict_types=1);

namespace App\Ministries\Application\Query\GetGroupWorkspace;

use App\Attendance\Application\Query\GetAttendance\GetAttendanceHandler;
use App\Attendance\Application\Query\GetAttendance\GetAttendanceQuery;
use App\Events\Application\ReadModel\OccurrenceDirectory;
use App\IdentityAccess\Application\Port\AuthenticatedAccount;
use App\Ministries\Application\ReadModel\MinistriesReadStore;
use App\Ministries\Domain\Model\GroupMeeting;
use App\People\Application\ReadModel\PersonDirectory;
use App\Shared\Application\Exception\AccessDenied;
use App\Shared\Application\Security\AccessControl;
use DateTimeImmutable;
use DateTimeZone;
use OutOfBoundsException;
use Symfony\Component\Clock\ClockInterface;

final readonly class GetGroupWorkspaceHandler
{
    public function __construct(
        private MinistriesReadStore $records,
        private PersonDirectory $people,
        private OccurrenceDirectory $occurrences,
        private AccessControl $authorization,
        private AuthenticatedAccount $tokens,
        private ClockInterface $clock,
        private GetAttendanceHandler $getAttendanceHandler,
    ) {
    }

    public function __invoke(GetGroupWorkspaceQuery $message): array
    {
        $id = $message->id;
        $group = $this->group($id);
        $members = [];
        $memberships = $this->records->all('memberships');
        $people = $this->people->getMany(array_column(array_filter($memberships, static fn ($m) => $m->ministryId === $id), 'personId'));
        foreach ($memberships as $m) {
            if ($m->ministryId === $id) {
                $members[] = ['id' => $m->id, 'personId' => $m->personId, 'name' => $people[$m->personId]['name'] ?? 'Person unavailable', 'role' => $m->role, 'active' => $m->active, 'joinedOn' => $m->joinedOn, 'leftOn' => $m->leftOn];
            }
        }
        $meetings = [];
        $groupMeetings = array_filter($this->records->all('meetings'), static fn ($meeting) => $meeting->ministryId === $id);
        $occurrences = $this->occurrences->getMany(array_column($groupMeetings, 'occurrenceId'));
        $attendance = ($this->getAttendanceHandler)(new GetAttendanceQuery());
        $entriesByOccurrence = [];
        $childrenByOccurrence = [];
        foreach ($attendance['entries'] as $entry) {
            $entriesByOccurrence[$entry->occurrenceId][] = $entry;
        }
        foreach ($attendance['children'] as $child) {
            $childrenByOccurrence[$child['occurrenceId']][] = $child;
        }
        $recordedIds = [];
        foreach ($groupMeetings as $meeting) {
            array_push($recordedIds, ...$meeting->recordedPersonIds);
        }
        $people += $this->people->getMany($recordedIds);
        foreach ($groupMeetings as $meeting) {
            if ($meeting->ministryId === $id) {
                $meetings[] = $this->meetingOverview($meeting, $occurrences[$meeting->occurrenceId] ?? throw new OutOfBoundsException('Occurrence not found.'), $memberships, $people, ['entries' => $entriesByOccurrence[$meeting->occurrenceId] ?? [], 'children' => $childrenByOccurrence[$meeting->occurrenceId] ?? []]);
            }
        }
        usort($meetings, fn ($a, $b) => [$a['occurrence']['startsAt'], $a['meeting']->id] <=> [$b['occurrence']['startsAt'], $b['meeting']->id]);

        return ['group' => $group, 'members' => $members, 'meetings' => $meetings, 'canManage' => $this->authorization->isGranted('ministries.manage')];
    }

    private function group(string $id): object
    {
        $g = $this->records->find('ministries', $id) ?? throw new OutOfBoundsException('Group not found.');
        if (!$this->allowed($g)) {
            throw new AccessDenied('This group is outside your leadership scope.');
        }

        return $g;
    }

    private function meetingOverview(GroupMeeting $meeting, array $occurrence, array $memberships, array $people, array $attendance): array
    {
        $day = (new DateTimeImmutable($occurrence['startsAt'], new DateTimeZone('UTC')))->setTimezone(new DateTimeZone($occurrence['timezone']))->format('Y-m-d');
        $roster = [];
        foreach ($memberships as $member) {
            if ($member->ministryId === $meeting->ministryId && $member->joinedOn <= $day && (null === $member->leftOn || $member->leftOn > $day)) {
                $roster[$member->personId] = ['personId' => $member->personId, 'name' => $people[$member->personId]['name'] ?? 'Person unavailable', 'attendance' => null, 'childPresent' => false];
            }
        }
        // Keep recorded history visible if a reschedule changes the eligible membership date.
        foreach ($attendance['entries'] as $entry) {
            if (!isset($roster[$entry->personId]) && !in_array($entry->personId, $meeting->recordedPersonIds, true)) {
                continue;
            }
            $roster[$entry->personId] ??= ['personId' => $entry->personId, 'name' => $people[$entry->personId]['name'] ?? 'Person unavailable', 'attendance' => null, 'childPresent' => false];
            $roster[$entry->personId]['attendance'] = $entry;
        }
        foreach ($attendance['children'] as $child) {
            if (isset($roster[$child['personId']])) {
                $roster[$child['personId']]['childPresent'] = true;
            }
        }
        $counts = ['present' => 0, 'absent' => 0, 'excused' => 0, 'unrecorded' => 0];
        foreach ($roster as $row) {
            ++$counts[$row['childPresent'] ? 'present' : $row['attendance']?->status ?? 'unrecorded'];
        }

        return ['meeting' => $meeting, 'occurrence' => $occurrence, 'roster' => array_values($roster), 'counts' => $counts];
    }

    private function allowed(object $group): bool
    {
        if ($this->authorization->isGranted('ministries.manage')) {
            return true;
        }
        $user = $this->tokens->user();
        if (!$group->active || !$this->authorization->isGranted('ministries.lead') || null === $user || 'active' !== $user->status()->value || null === $user->personId()) {
            return false;
        }
        foreach ($this->records->all('memberships') as $m) {
            if ($m->ministryId === $group->id && $m->personId === $user->personId() && $m->active && 'leader' === $m->role && $m->joinedOn <= $this->clock->now()->format('Y-m-d')) {
                return true;
            }
        }

        return false;
    }
}

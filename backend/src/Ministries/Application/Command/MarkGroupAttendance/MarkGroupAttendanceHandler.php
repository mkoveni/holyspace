<?php

declare(strict_types=1);

namespace App\Ministries\Application\Command\MarkGroupAttendance;

use App\Attendance\Application\Command\CorrectAttendance\CorrectAttendanceCommand;
use App\Attendance\Application\Command\CorrectAttendance\CorrectAttendanceHandler;
use App\Attendance\Application\Command\RecordAttendance\RecordAttendanceCommand;
use App\Attendance\Application\Command\RecordAttendance\RecordAttendanceHandler;
use App\Attendance\Application\Query\GetAttendance\GetAttendanceHandler;
use App\Attendance\Application\Query\GetAttendance\GetAttendanceQuery;
use App\Events\Application\ReadModel\OccurrenceDirectory;
use App\IdentityAccess\Application\Port\AuthenticatedAccount;
use App\Ministries\Domain\Model\GroupMeeting;
use App\Ministries\Domain\Repository\MinistriesRepository;
use App\People\Application\ReadModel\PersonDirectory;
use App\Shared\Application\Exception\AccessDenied;
use App\Shared\Application\Security\AccessControl;
use App\Shared\Application\WorkflowTransaction;
use DateTimeImmutable;
use DateTimeZone;
use DomainException;
use InvalidArgumentException;
use OutOfBoundsException;
use Symfony\Component\Clock\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

final readonly class MarkGroupAttendanceHandler
{
    public function __construct(
        private MinistriesRepository $records,
        private PersonDirectory $people,
        private OccurrenceDirectory $occurrences,
        private WorkflowTransaction $transactions,
        private AccessControl $authorization,
        private AuthenticatedAccount $tokens,
        private ClockInterface $clock,
        private GetAttendanceHandler $getAttendanceHandler,
        private CorrectAttendanceHandler $correctAttendanceHandler,
        private RecordAttendanceHandler $recordAttendanceHandler,
    ) {
    }

    #[AsMessageHandler]
    public function __invoke(MarkGroupAttendanceCommand $message): object
    {
        $groupId = $message->groupId;
        $id = $message->id;
        $personId = $message->personId;
        $status = $message->status;
        $expectedVersion = $message->expectedVersion;
        $reason = $message->reason;

        return $this->transactions->run(function () use ($groupId, $id, $personId, $status, $expectedVersion, $reason) {
            $group = $this->group($groupId);
            $meeting = $this->meeting($groupId, $id);
            $occurrence = $this->occurrences->get($meeting->occurrenceId) ?? throw new OutOfBoundsException('Occurrence not found.');
            $memberships = $this->records->all('memberships');
            $people = $this->people->getMany([...array_column(array_filter($memberships, static fn ($m) => $m->ministryId === $groupId), 'personId'), ...$meeting->recordedPersonIds]);
            $view = $this->meetingOverview($meeting, $occurrence, $memberships, $people, ($this->getAttendanceHandler)(new GetAttendanceQuery(null, $meeting->occurrenceId)));
            $row = array_values(array_filter($view['roster'], fn ($r) => $r['personId'] === $personId))[0] ?? throw new InvalidArgumentException('Person is not on this meeting roster.');
            $existing = $row['attendance'];
            if (null !== $existing) {
                if ($expectedVersion !== $existing->version) {
                    throw new DomainException('Attendance changed. Reload before retrying.');
                }
                $result = ($this->correctAttendanceHandler)(new CorrectAttendanceCommand($existing->id, $status, $reason ?? ''));
                if (!in_array($personId, $meeting->recordedPersonIds, true)) {
                    $this->records->save($meeting->recordPerson($personId));
                }

                return $result;
            }
            if (null !== $expectedVersion) {
                throw new DomainException('Attendance changed. Reload before retrying.');
            }
            if (!$group->active || 'cancelled' === $meeting->status || 'cancelled' === $view['occurrence']['status']) {
                throw new DomainException('This meeting is closed to new attendance.');
            }
            $result = ($this->recordAttendanceHandler)(new RecordAttendanceCommand(['occurrenceId' => $meeting->occurrenceId, 'personId' => $personId, 'status' => $status]));
            $this->records->save($meeting->recordPerson($personId));

            return $result;
        });
    }

    private function group(string $id): object
    {
        $g = $this->records->find('ministries', $id) ?? throw new OutOfBoundsException('Group not found.');
        if (!$this->allowed($g)) {
            throw new AccessDenied('This group is outside your leadership scope.');
        }

        return $g;
    }

    private function meeting(string $groupId, string $id): GroupMeeting
    {
        $m = $this->records->find('meetings', $id);
        if (!$m || $m->ministryId !== $groupId) {
            throw new OutOfBoundsException('Meeting not found for this group.');
        }

        return $m;
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

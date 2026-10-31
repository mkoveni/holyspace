<?php

declare(strict_types=1);

namespace App\Volunteering\Application\Command\ChangeAssignmentStatus;

use App\Events\Application\ReadModel\OccurrenceDirectory;
use App\Shared\Application\WorkflowTransaction;
use App\Shared\Domain\ValueObject\Input;
use App\Volunteering\Application\Command\SettleVolunteerInvitations\SettleVolunteerInvitationsCommand;
use App\Volunteering\Application\Command\SettleVolunteerInvitations\SettleVolunteerInvitationsHandler;
use App\Volunteering\Domain\Model\ShiftRequirement;
use App\Volunteering\Domain\Repository\VolunteeringRepository;
use App\Volunteering\Domain\Service\VolunteerEligibility;
use DomainException;
use OutOfBoundsException;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

final readonly class ChangeAssignmentStatusHandler
{
    public function __construct(
        private VolunteeringRepository $records,
        private OccurrenceDirectory $occurrences,
        private WorkflowTransaction $transactions,
        private SettleVolunteerInvitationsHandler $settleVolunteerInvitationsHandler,
    ) {
    }

    #[AsMessageHandler]
    public function __invoke(ChangeAssignmentStatusCommand $message): object
    {
        $id = $message->id;
        $status = $message->status;

        return $this->mutate(function () use ($id, $status) {
            $record = $this->required('assignments', $id);
            if ('assigned' === $status) {
                $this->checkAssignment($this->required('shifts', $record->shiftId), $record->personId, $id);
            }
            $this->records->save($record->changeStatus($status));

            return $this->required('assignments', $id);
        });
    }

    private function mutate(callable $operation): mixed
    {
        ($this->settleVolunteerInvitationsHandler)(new SettleVolunteerInvitationsCommand());

        return $this->transactions->run($operation);
    }

    private function required(string $kind, string $id): object
    {
        return $this->records->find($kind, $id) ?? throw new OutOfBoundsException('Record not found.');
    }

    private function checkAssignment(ShiftRequirement $shift, string $personId, ?string $ignoreId = null): void
    {
        $occurrence = $this->occurrences->get($shift->occurrenceId);
        if ('open' !== $shift->status || !$occurrence || 'cancelled' === $occurrence['status']) {
            throw new DomainException('Shift is not open for assignment.');
        }
        if (Input::instant($shift->startsAt) < Input::instant($occurrence['startsAt']) || Input::instant($shift->endsAt) > Input::instant($occurrence['endsAt'])) {
            throw new DomainException('Review shift times after rescheduling the event.');
        }
        $profile = null;
        foreach ($this->records->all('profiles') as $candidate) {
            if ($candidate->personId === $personId) {
                $profile = $candidate;
            }
        }
        if (!$profile) {
            throw new DomainException('Create a volunteer profile first.');
        }
        VolunteerEligibility::requireEligible($profile, $this->required('roles', $shift->roleId), $shift);
        $filled = 0;
        foreach ($this->records->all('assignments') as $assignment) {
            if ($assignment->id === $ignoreId || !in_array($assignment->status, ['invited', 'assigned', 'needs_review'], true)) {
                continue;
            }
            if ($assignment->shiftId === $shift->id) {
                ++$filled;
                if ($assignment->personId === $personId) {
                    throw new DomainException('Volunteer is already assigned to this shift.');
                }
            }
            if ($assignment->personId === $personId) {
                $other = $this->required('shifts', $assignment->shiftId);
                if (Input::overlaps($shift->startsAt, $shift->endsAt, $other->startsAt, $other->endsAt)) {
                    throw new DomainException('Volunteer has an overlapping assignment.');
                }
            }
        }
        if ($filled >= $shift->positions) {
            throw new DomainException('All positions are filled.');
        }
    }
}

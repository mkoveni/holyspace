<?php

declare(strict_types=1);

namespace App\Volunteering\Application\Command\SaveVolunteerShift;

use App\Events\Application\ReadModel\OccurrenceDirectory;
use App\Shared\Application\WorkflowTransaction;
use App\Shared\Domain\ValueObject\Input;
use App\Volunteering\Application\Command\SettleVolunteerInvitations\SettleVolunteerInvitationsCommand;
use App\Volunteering\Application\Command\SettleVolunteerInvitations\SettleVolunteerInvitationsHandler;
use App\Volunteering\Domain\Model\ShiftRequirement;
use App\Volunteering\Domain\Repository\VolunteeringRepository;
use DateTimeImmutable;
use DomainException;
use InvalidArgumentException;
use OutOfBoundsException;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

final readonly class SaveVolunteerShiftHandler
{
    public function __construct(
        private VolunteeringRepository $records,
        private OccurrenceDirectory $occurrences,
        private WorkflowTransaction $transactions,
        private SettleVolunteerInvitationsHandler $settleVolunteerInvitationsHandler,
    ) {
    }

    #[AsMessageHandler]
    public function __invoke(SaveVolunteerShiftCommand $message): object
    {
        $data = $message->data;
        $id = $message->id;

        return $this->mutate(function () use ($data, $id) {
            $occurrence = $this->occurrences->get($data['occurrenceId']) ?? throw new InvalidArgumentException('Occurrence not found.');
            if ('cancelled' === $occurrence['status'] && 'cancelled' !== $data['status']) {
                throw new DomainException('Cannot staff a cancelled occurrence.');
            }
            $this->required('roles', $data['roleId']);
            if (Input::instant($data['startsAt']) < Input::instant($occurrence['startsAt']) || Input::instant($data['endsAt']) > Input::instant($occurrence['endsAt'])) {
                throw new InvalidArgumentException('Shift must be within its event occurrence.');
            }
            $old = null === $id ? null : $this->required('shifts', $id);
            if ($old && ($old->occurrenceId !== $data['occurrenceId'] || $old->roleId !== $data['roleId'])) {
                throw new DomainException('Shift occurrence and role cannot be changed.');
            }
            if ($old) {
                $filled = count(array_filter($this->records->all('assignments'), fn ($a) => $a->shiftId === $id && in_array($a->status, ['invited', 'assigned', 'needs_review'], true)));
                if ($data['positions'] < $filled && 'cancelled' !== $data['status']) {
                    throw new DomainException('Positions cannot be reduced below existing assignments.');
                }
            }
            $record = new ShiftRequirement(...$data + ($old ? ['id' => $old->id, 'createdAt' => $old->createdAt, 'updatedAt' => (new DateTimeImmutable())->format(DATE_ATOM), 'version' => $old->version] : []));
            $this->records->save($record);
            if ($old) {
                foreach ($this->records->all('assignments') as $assignment) {
                    if ($assignment->shiftId === $id && in_array($assignment->status, ['invited', 'assigned', 'needs_review'], true)) {
                        $this->records->save($assignment->changeStatus('cancelled' === $record->status ? 'cancelled' : 'needs_review'));
                    }
                }
            }

            return $this->required('shifts', $record->id);
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
}

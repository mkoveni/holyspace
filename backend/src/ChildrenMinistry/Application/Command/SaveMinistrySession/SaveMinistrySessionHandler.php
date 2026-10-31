<?php

declare(strict_types=1);

namespace App\ChildrenMinistry\Application\Command\SaveMinistrySession;

use App\ChildrenMinistry\Domain\Model\MinistrySession;
use App\ChildrenMinistry\Domain\Repository\ChildrenMinistryRepository;
use App\Events\Application\ReadModel\OccurrenceDirectory;
use App\Shared\Application\Outbox\IntegrationProcessor;
use App\Shared\Application\WorkflowTransaction;
use App\Volunteering\Application\ReadModel\StaffingDirectory;
use DomainException;
use InvalidArgumentException;
use OutOfBoundsException;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

final readonly class SaveMinistrySessionHandler
{
    public function __construct(
        private ChildrenMinistryRepository $records,
        private OccurrenceDirectory $occurrences,
        private StaffingDirectory $staffing,
        private WorkflowTransaction $transactions,
        private IntegrationProcessor $outbox,
    ) {
    }

    #[AsMessageHandler]
    public function __invoke(SaveMinistrySessionCommand $message): object
    {
        $data = $message->data;
        $id = $message->id;

        return $this->mutate(function () use ($data, $id) {
            $existing = null === $id ? null : $this->required('sessions', $id);
            if ($existing && ($existing->classId !== $data['classId'] || $existing->occurrenceId !== $data['occurrenceId'])) {
                throw new DomainException('Session class and occurrence cannot change.');
            }
            if ($existing) {
                foreach ($this->records->all('checkins') as $checkin) {
                    if ($checkin->sessionId === $id && null === $checkin->checkedOutAt && [] !== array_diff($existing->staffPersonIds, $data['staffPersonIds'])) {
                        throw new DomainException('Keep supervising staff while children remain checked in.');
                    }
                }
            }
            $this->required('classes', $data['classId']);
            $occurrence = $this->occurrences->get($data['occurrenceId']) ?? throw new InvalidArgumentException('Occurrence not found.');
            if ('cancelled' === $occurrence['status']) {
                throw new DomainException('Cannot create a session for a cancelled occurrence.');
            }
            foreach ($data['staffPersonIds'] as $personId) {
                if (!$this->staffing->eligibleForOccurrence($personId, $data['occurrenceId'])) {
                    throw new DomainException('Every staff member needs a confirmed, eligible volunteer assignment in a screening-required role for this event occurrence.');
                }
            }
            foreach ($this->records->all('sessions') as $session) {
                if ($session->id !== $id && $session->classId === $data['classId'] && $session->occurrenceId === $data['occurrenceId'] && 'cancelled' !== $session->status) {
                    throw new DomainException('This class already has a session for this occurrence.');
                }
            }

            return $this->save('sessions', $existing ? $existing->confirmStaff($data['staffPersonIds']) : new MinistrySession(...$data));
        });
    }

    private function mutate(callable $operation): mixed
    {
        $this->outbox->drainAll();

        return $this->transactions->run($operation);
    }

    private function required(string $kind, string $id): object
    {
        return $this->records->find($kind, $id) ?? throw new OutOfBoundsException('Record not found.');
    }

    private function save(string $kind, object $record): object
    {
        $this->records->save($record);

        return $this->required($kind, $record->id);
    }
}

<?php

declare(strict_types=1);

namespace App\Attendance\Application\Command\RecordAttendance;

use App\Attendance\Domain\Model\AttendanceEntry;
use App\Attendance\Domain\Repository\AttendanceRepository;
use App\ChildrenMinistry\Application\ReadModel\ChildAttendanceDirectory;
use App\Events\Application\ReadModel\OccurrenceDirectory;
use App\People\Application\ReadModel\PersonDirectory;
use App\Shared\Application\WorkflowTransaction;
use DomainException;
use InvalidArgumentException;
use OutOfBoundsException;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

final readonly class RecordAttendanceHandler
{
    public function __construct(
        private AttendanceRepository $records,
        private PersonDirectory $people,
        private OccurrenceDirectory $occurrences,
        private ChildAttendanceDirectory $children,
        private WorkflowTransaction $transactions,
    ) {
    }

    #[AsMessageHandler]
    public function __invoke(RecordAttendanceCommand $message): object
    {
        $data = $message->data;

        return $this->transactions->run(function () use ($data) {
            $this->openOccurrence($data['occurrenceId']);
            if (!$this->people->get($data['personId'])) {
                throw new InvalidArgumentException('Person not found.');
            }
            foreach ($this->records->all('entries') as $entry) {
                if ($entry->personId === $data['personId'] && $entry->occurrenceId === $data['occurrenceId']) {
                    throw new DomainException('Attendance already recorded. Use a correction.');
                }
            }
            $this->checkChildPresence($data['personId'], $data['occurrenceId'], $data['status']);

            return $this->save('entries', new AttendanceEntry(...$data));
        });
    }

    private function openOccurrence(string $id): void
    {
        $occurrence = $this->occurrences->get($id) ?? throw new InvalidArgumentException('Occurrence not found.');
        if ('cancelled' === $occurrence['status']) {
            throw new DomainException('Cannot add attendance to a cancelled occurrence.');
        }
    }

    private function checkChildPresence(string $personId, string $occurrenceId, string $status): void
    {
        if ('present' === $status) {
            return;
        }
        foreach ($this->children->forOccurrence($occurrenceId) as $child) {
            if ($child['personId'] === $personId) {
                throw new DomainException('A children’s session check-in records this person as present.');
            }
        }
    }

    private function save(string $kind, object $record): object
    {
        $this->records->save($record);

        return $this->required($kind, $record->id);
    }

    private function required(string $kind, string $id): object
    {
        return $this->records->find($kind, $id) ?? throw new OutOfBoundsException('Record not found.');
    }
}

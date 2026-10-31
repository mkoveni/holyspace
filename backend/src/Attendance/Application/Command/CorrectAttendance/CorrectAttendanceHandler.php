<?php

declare(strict_types=1);

namespace App\Attendance\Application\Command\CorrectAttendance;

use App\Attendance\Domain\Repository\AttendanceRepository;
use App\ChildrenMinistry\Application\ReadModel\ChildAttendanceDirectory;
use App\Shared\Application\WorkflowTransaction;
use DomainException;
use OutOfBoundsException;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

final readonly class CorrectAttendanceHandler
{
    public function __construct(
        private AttendanceRepository $records,
        private ChildAttendanceDirectory $children,
        private WorkflowTransaction $transactions,
    ) {
    }

    #[AsMessageHandler]
    public function __invoke(CorrectAttendanceCommand $message): object
    {
        $id = $message->id;
        $status = $message->status;
        $reason = $message->reason;

        return $this->transactions->run(function () use ($id, $status, $reason) {
            $entry = $this->required('entries', $id);
            $this->checkChildPresence($entry->personId, $entry->occurrenceId, $status);

            return $this->save('entries', $entry->correct($status, $reason));
        });
    }

    private function required(string $kind, string $id): object
    {
        return $this->records->find($kind, $id) ?? throw new OutOfBoundsException('Record not found.');
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
}

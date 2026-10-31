<?php

declare(strict_types=1);

namespace App\Attendance\Application\Command\RecordVisitors;

use App\Attendance\Domain\Model\VisitorCount;
use App\Attendance\Domain\Repository\AttendanceRepository;
use App\Events\Application\ReadModel\OccurrenceDirectory;
use App\Shared\Application\WorkflowTransaction;
use DomainException;
use InvalidArgumentException;
use OutOfBoundsException;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

final readonly class RecordVisitorsHandler
{
    public function __construct(
        private AttendanceRepository $records,
        private OccurrenceDirectory $occurrences,
        private WorkflowTransaction $transactions,
    ) {
    }

    #[AsMessageHandler]
    public function __invoke(RecordVisitorsCommand $message): object
    {
        $data = $message->data;

        return $this->transactions->run(function () use ($data) {
            $this->openOccurrence($data['occurrenceId']);
            foreach ($this->records->all('visitors') as $count) {
                if ($count->occurrenceId === $data['occurrenceId']) {
                    throw new DomainException('Visitor count already exists. Use a correction.');
                }
            }

            return $this->save('visitors', new VisitorCount(...$data));
        });
    }

    private function openOccurrence(string $id): void
    {
        $occurrence = $this->occurrences->get($id) ?? throw new InvalidArgumentException('Occurrence not found.');
        if ('cancelled' === $occurrence['status']) {
            throw new DomainException('Cannot add attendance to a cancelled occurrence.');
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

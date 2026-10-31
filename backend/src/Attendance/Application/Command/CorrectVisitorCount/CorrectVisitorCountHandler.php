<?php

declare(strict_types=1);

namespace App\Attendance\Application\Command\CorrectVisitorCount;

use App\Attendance\Domain\Repository\AttendanceRepository;
use App\Shared\Application\WorkflowTransaction;
use OutOfBoundsException;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

final readonly class CorrectVisitorCountHandler
{
    public function __construct(private AttendanceRepository $records, private WorkflowTransaction $transactions)
    {
    }

    #[AsMessageHandler]
    public function __invoke(CorrectVisitorCountCommand $message): object
    {
        $id = $message->id;
        $count = $message->count;
        $reason = $message->reason;

        return $this->transactions->run(fn () => $this->save('visitors', $this->required('visitors', $id)->correct($count, $reason)));
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

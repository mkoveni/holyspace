<?php

declare(strict_types=1);

namespace App\Ministries\Application\Command\LeaveMinistry;

use App\Ministries\Domain\Repository\MinistriesRepository;
use App\Shared\Application\WorkflowTransaction;
use OutOfBoundsException;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

final readonly class LeaveMinistryHandler
{
    public function __construct(private MinistriesRepository $records, private WorkflowTransaction $transactions)
    {
    }

    #[AsMessageHandler]
    public function __invoke(LeaveMinistryCommand $message): object
    {
        $id = $message->id;
        $date = $message->date;

        return $this->transactions->run(fn () => $this->save('memberships', $this->required('memberships', $id)->leave($date)));
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

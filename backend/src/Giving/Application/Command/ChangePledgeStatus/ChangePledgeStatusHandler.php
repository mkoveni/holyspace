<?php

declare(strict_types=1);

namespace App\Giving\Application\Command\ChangePledgeStatus;

use App\Giving\Domain\Repository\GivingRepository;
use App\Shared\Application\WorkflowTransaction;
use OutOfBoundsException;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

final readonly class ChangePledgeStatusHandler
{
    public function __construct(private GivingRepository $records, private WorkflowTransaction $transactions)
    {
    }

    #[AsMessageHandler]
    public function __invoke(ChangePledgeStatusCommand $message): object
    {
        $id = $message->id;
        $status = $message->status;

        return $this->transactions->run(fn () => $this->save('pledges', $this->required('pledges', $id)->changeStatus($status)));
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

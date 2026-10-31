<?php

declare(strict_types=1);

namespace App\Communications\Application\Command\ResolveDelivery;

use App\Communications\Domain\Repository\CommunicationsRepository;
use App\Shared\Application\WorkflowTransaction;
use OutOfBoundsException;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

final readonly class ResolveDeliveryHandler
{
    public function __construct(private CommunicationsRepository $records, private WorkflowTransaction $transactions)
    {
    }

    #[AsMessageHandler]
    public function __invoke(ResolveDeliveryCommand $message): object
    {
        $id = $message->id;
        $status = $message->status;
        $note = $message->note;

        return $this->transactions->run(fn () => $this->save('deliveries', $this->required('deliveries', $id)->resolve($status, $note)));
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

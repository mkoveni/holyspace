<?php

declare(strict_types=1);

namespace App\ChildrenMinistry\Application\Command\CancelMinistrySession;

use App\ChildrenMinistry\Domain\Repository\ChildrenMinistryRepository;
use App\Shared\Application\Outbox\IntegrationProcessor;
use App\Shared\Application\WorkflowTransaction;
use OutOfBoundsException;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

final readonly class CancelMinistrySessionHandler
{
    public function __construct(
        private ChildrenMinistryRepository $records,
        private WorkflowTransaction $transactions,
        private IntegrationProcessor $outbox,
    ) {
    }

    #[AsMessageHandler]
    public function __invoke(CancelMinistrySessionCommand $message): object
    {
        $id = $message->id;

        return $this->mutate(fn () => $this->save('sessions', $this->required('sessions', $id)->changeStatus('cancelled')));
    }

    private function mutate(callable $operation): mixed
    {
        $this->outbox->drainAll();

        return $this->transactions->run($operation);
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

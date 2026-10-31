<?php

declare(strict_types=1);

namespace App\ChildrenMinistry\Application\Command\DeactivateChildRecord;

use App\ChildrenMinistry\Domain\Repository\ChildrenMinistryRepository;
use App\Shared\Application\Outbox\IntegrationProcessor;
use App\Shared\Application\WorkflowTransaction;
use OutOfBoundsException;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

final readonly class DeactivateChildRecordHandler
{
    public function __construct(
        private ChildrenMinistryRepository $records,
        private WorkflowTransaction $transactions,
        private IntegrationProcessor $outbox,
    ) {
    }

    #[AsMessageHandler]
    public function __invoke(DeactivateChildRecordCommand $message): object
    {
        $kind = $message->kind;
        $id = $message->id;

        return $this->mutate(function () use ($kind, $id) {
            $record = $this->required($kind, $id);

            return $this->save($kind, 'enrolments' === $kind ? $record->deactivate() : $record->revoke());
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

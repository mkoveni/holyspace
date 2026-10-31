<?php

declare(strict_types=1);

namespace App\ChildrenMinistry\Application\Command\SaveMinistryClass;

use App\ChildrenMinistry\Domain\Model\MinistryClass;
use App\ChildrenMinistry\Domain\Repository\ChildrenMinistryRepository;
use App\Shared\Application\Outbox\IntegrationProcessor;
use App\Shared\Application\WorkflowTransaction;
use OutOfBoundsException;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

final readonly class SaveMinistryClassHandler
{
    public function __construct(
        private ChildrenMinistryRepository $records,
        private WorkflowTransaction $transactions,
        private IntegrationProcessor $outbox,
    ) {
    }

    #[AsMessageHandler]
    public function __invoke(SaveMinistryClassCommand $message): object
    {
        $data = $message->data;

        return $this->mutate(function () use ($data) {
            $this->required('programmes', $data['programmeId']);

            return $this->save('classes', new MinistryClass(...$data));
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

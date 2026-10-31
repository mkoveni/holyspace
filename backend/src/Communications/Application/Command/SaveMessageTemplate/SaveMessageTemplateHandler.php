<?php

declare(strict_types=1);

namespace App\Communications\Application\Command\SaveMessageTemplate;

use App\Communications\Domain\Model\MessageTemplate;
use App\Communications\Domain\Repository\CommunicationsRepository;
use App\Shared\Application\WorkflowTransaction;
use OutOfBoundsException;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

final readonly class SaveMessageTemplateHandler
{
    public function __construct(private CommunicationsRepository $records, private WorkflowTransaction $transactions)
    {
    }

    #[AsMessageHandler]
    public function __invoke(SaveMessageTemplateCommand $message): object
    {
        $data = $message->data;

        return $this->transactions->run(fn () => $this->save('templates', new MessageTemplate(...$data)));
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

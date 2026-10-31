<?php

declare(strict_types=1);

namespace App\Ministries\Application\Command\SaveMinistry;

use App\Ministries\Domain\Model\Ministry;
use App\Ministries\Domain\Repository\MinistriesRepository;
use App\Shared\Application\WorkflowTransaction;
use OutOfBoundsException;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

final readonly class SaveMinistryHandler
{
    public function __construct(private MinistriesRepository $records, private WorkflowTransaction $transactions)
    {
    }

    #[AsMessageHandler]
    public function __invoke(SaveMinistryCommand $message): object
    {
        $data = $message->data;

        return $this->transactions->run(fn () => $this->save('ministries', new Ministry(...$data)));
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

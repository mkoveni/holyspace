<?php

declare(strict_types=1);

namespace App\Ministries\Application\Command\ArchiveMinistry;

use App\Ministries\Domain\Repository\MinistriesRepository;
use App\Shared\Application\WorkflowTransaction;
use OutOfBoundsException;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

final readonly class ArchiveMinistryHandler
{
    public function __construct(private MinistriesRepository $records, private WorkflowTransaction $transactions)
    {
    }

    #[AsMessageHandler]
    public function __invoke(ArchiveMinistryCommand $message): object
    {
        $id = $message->id;

        return $this->transactions->run(fn () => $this->save('ministries', $this->required('ministries', $id)->archive()));
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

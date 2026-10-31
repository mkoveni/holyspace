<?php

declare(strict_types=1);

namespace App\Giving\Application\Command\CreateFund;

use App\Giving\Domain\Model\Fund;
use App\Giving\Domain\Repository\GivingRepository;
use App\Shared\Application\WorkflowTransaction;
use OutOfBoundsException;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

final readonly class CreateFundHandler
{
    public function __construct(private GivingRepository $records, private WorkflowTransaction $transactions)
    {
    }

    #[AsMessageHandler]
    public function __invoke(CreateFundCommand $message): object
    {
        $name = $message->name;

        return $this->transactions->run(fn () => $this->save('funds', new Fund($name)));
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

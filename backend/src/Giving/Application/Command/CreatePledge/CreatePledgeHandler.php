<?php

declare(strict_types=1);

namespace App\Giving\Application\Command\CreatePledge;

use App\Giving\Domain\Model\Pledge;
use App\Giving\Domain\Repository\GivingRepository;
use App\People\Application\ReadModel\PersonDirectory;
use App\Shared\Application\WorkflowTransaction;
use InvalidArgumentException;
use OutOfBoundsException;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

final readonly class CreatePledgeHandler
{
    public function __construct(
        private GivingRepository $records,
        private PersonDirectory $people,
        private WorkflowTransaction $transactions,
    ) {
    }

    #[AsMessageHandler]
    public function __invoke(CreatePledgeCommand $message): object
    {
        $data = $message->data;

        return $this->transactions->run(function () use ($data) {
            if (!$this->people->get($data['donorId'])) {
                throw new InvalidArgumentException('Donor not found.');
            }
            $this->required('funds', $data['fundId']);

            return $this->save('pledges', new Pledge(...$data));
        });
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

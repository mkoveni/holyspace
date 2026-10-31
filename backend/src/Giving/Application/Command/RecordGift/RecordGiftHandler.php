<?php

declare(strict_types=1);

namespace App\Giving\Application\Command\RecordGift;

use App\Events\Application\ReadModel\OccurrenceDirectory;
use App\Giving\Domain\Model\Gift;
use App\Giving\Domain\Repository\GivingRepository;
use App\People\Application\ReadModel\PersonDirectory;
use App\Shared\Application\WorkflowTransaction;
use DomainException;
use InvalidArgumentException;
use OutOfBoundsException;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

final readonly class RecordGiftHandler
{
    public function __construct(
        private GivingRepository $records,
        private PersonDirectory $people,
        private OccurrenceDirectory $occurrences,
        private WorkflowTransaction $transactions,
    ) {
    }

    #[AsMessageHandler]
    public function __invoke(RecordGiftCommand $message): object
    {
        $data = $message->data;

        return $this->transactions->run(function () use ($data) {
            $candidate = new Gift(...$data);
            foreach ($this->records->all('gifts') as $gift) {
                if ($gift->idempotencyKey === $candidate->idempotencyKey) {
                    foreach ($data as $key => $value) {
                        if ($gift->{$key} !== $candidate->{$key}) {
                            throw new DomainException('Idempotency key was already used for a different donation.');
                        }
                    }

                    return $gift;
                }
                if (null !== $candidate->externalReference && $gift->externalReference === $candidate->externalReference) {
                    throw new DomainException('External reference has already been recorded.');
                }
            }
            if (null !== $candidate->donorId && !$this->people->get($candidate->donorId)) {
                throw new InvalidArgumentException('Donor not found.');
            }
            if (null !== $candidate->occurrenceId && !$this->occurrences->get($candidate->occurrenceId)) {
                throw new InvalidArgumentException('Occurrence not found.');
            }
            foreach ($candidate->allocations as $allocation) {
                if (!$this->required('funds', $allocation['fundId'])->active) {
                    throw new DomainException('Fund is inactive.');
                }
            }

            return $this->save('gifts', $candidate);
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

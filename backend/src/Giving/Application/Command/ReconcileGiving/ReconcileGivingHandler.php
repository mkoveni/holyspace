<?php

declare(strict_types=1);

namespace App\Giving\Application\Command\ReconcileGiving;

use App\Giving\Domain\Model\ReconciliationBatch;
use App\Giving\Domain\Repository\GivingRepository;
use App\Shared\Application\WorkflowTransaction;
use DomainException;
use InvalidArgumentException;
use OutOfBoundsException;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

final readonly class ReconcileGivingHandler
{
    public function __construct(private GivingRepository $records, private WorkflowTransaction $transactions)
    {
    }

    #[AsMessageHandler]
    public function __invoke(ReconcileGivingCommand $message): object
    {
        $data = $message->data;

        return $this->transactions->run(function () use ($data) {
            $batch = new ReconciliationBatch(...$data);
            foreach ($this->records->all('batches') as $existing) {
                if ($existing->reference === $batch->reference) {
                    throw new DomainException('Reconciliation reference already exists.');
                }
                if ([] !== array_intersect($existing->giftIds, $batch->giftIds)) {
                    throw new DomainException('A selected gift is already reconciled.');
                }
            }
            $total = 0;
            foreach ($batch->giftIds as $giftId) {
                $gift = $this->required('gifts', $giftId);
                if ('posted' !== $gift->status || $gift->currency !== $batch->currency) {
                    throw new DomainException('All reconciled gifts must be posted in the batch currency.');
                }
                if ($gift->receivedOn > $batch->reconciledOn) {
                    throw new InvalidArgumentException('Reconciliation cannot precede a selected gift.');
                }
                $total += $gift->amountMinor;
            }
            if ($total !== $batch->expectedAmountMinor) {
                throw new DomainException('Gift total does not match the expected reconciled amount.');
            }

            return $this->save('batches', $batch);
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

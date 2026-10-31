<?php

declare(strict_types=1);

namespace App\Giving\Application\Command\ReverseGift;

use App\Giving\Domain\Model\GiftReversal;
use App\Giving\Domain\Repository\GivingRepository;
use App\Shared\Application\WorkflowTransaction;
use App\Shared\Domain\ValueObject\Input;
use DomainException;
use InvalidArgumentException;
use OutOfBoundsException;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

final readonly class ReverseGiftHandler
{
    public function __construct(private GivingRepository $records, private WorkflowTransaction $transactions)
    {
    }

    #[AsMessageHandler]
    public function __invoke(ReverseGiftCommand $message): object
    {
        $id = $message->id;
        $date = $message->date;
        $reason = $message->reason;

        return $this->transactions->run(function () use ($id, $date, $reason) {
            $gift = $this->required('gifts', $id);
            if ('online' === $gift->method) {
                throw new DomainException('Record verified online refunds in Online payments so the payment and giving ledgers stay reconciled.');
            }
            if ('posted' !== $gift->status) {
                throw new DomainException('Gift has already been reversed.');
            }
            if (Input::date($date)->format('Y-m-d') < $gift->receivedOn) {
                throw new InvalidArgumentException('Reversal cannot precede the gift.');
            }
            $remaining = $this->remainingAllocations($gift);
            $reversal = new GiftReversal($gift->id, array_sum(array_column($remaining, 'amountMinor')), $gift->currency, $remaining, $date, $reason);
            $this->records->save($gift->reverse());
            foreach ($this->records->all('receipts') as $receipt) {
                if ($receipt->giftId === $id) {
                    $this->records->save($receipt->voidReceipt());
                }
            }

            return $this->save('reversals', $reversal);
        });
    }

    private function required(string $kind, string $id): object
    {
        return $this->records->find($kind, $id) ?? throw new OutOfBoundsException('Record not found.');
    }

    private function remainingAllocations(object $gift): array
    {
        $amounts = array_column($gift->allocations, 'amountMinor', 'fundId');
        foreach ($this->records->all('reversals') as $r) {
            if ($r->giftId === $gift->id) {
                foreach ($r->allocations as $a) {
                    $amounts[$a['fundId']] -= $a['amountMinor'];
                }
            }
        }
        $remaining = [];
        foreach ($amounts as $id => $amount) {
            if ($amount > 0) {
                $remaining[] = ['fundId' => $id, 'amountMinor' => $amount];
            }
        }

        return $remaining;
    }

    private function save(string $kind, object $record): object
    {
        $this->records->save($record);

        return $this->required($kind, $record->id);
    }
}

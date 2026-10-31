<?php

declare(strict_types=1);

namespace App\Giving\Application\Command\RecordOnlineRefund;

use App\Giving\Domain\Model\Gift;
use App\Giving\Domain\Model\GiftReversal;
use App\Giving\Domain\Model\Pledge;
use App\Giving\Domain\Repository\GivingRepository;
use App\Shared\Application\WorkflowTransaction;
use App\Shared\Domain\ValueObject\Input;
use DomainException;
use InvalidArgumentException;
use OutOfBoundsException;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

final readonly class RecordOnlineRefundHandler
{
    public function __construct(private GivingRepository $records, private WorkflowTransaction $transactions)
    {
    }

    #[AsMessageHandler]
    public function __invoke(RecordOnlineRefundCommand $message): object
    {
        $giftId = $message->giftId;
        $amount = $message->amount;
        $reference = $message->reference;
        $date = $message->date;

        return $this->transactions->run(function () use ($giftId, $amount, $reference, $date) {
            foreach ($this->records->all('reversals') as $prior) {
                if ($prior->externalReference === $reference) {
                    if ($prior->giftId !== $giftId || $prior->amountMinor !== $amount) {
                        throw new DomainException('Refund reference conflicts with an existing reversal.');
                    }

                    return $prior;
                }
            }
            $gift = $this->required('gifts', $giftId);
            $remaining = $this->remainingAllocations($gift);
            if ('online' !== $gift->method || 1 !== count($gift->allocations) || $amount < 1 || $amount > array_sum(array_column($remaining, 'amountMinor'))) {
                throw new DomainException('Refund exceeds the remaining online gift amount. Review the ledger.');
            }
            if (Input::date($date)->format('Y-m-d') < $gift->receivedOn) {
                throw new InvalidArgumentException('Refund cannot precede the gift.');
            }
            $reversal = new GiftReversal($giftId, $amount, $gift->currency, [['fundId' => $gift->allocations[0]['fundId'], 'amountMinor' => $amount]], $date, 'Verified online payment refund.', externalReference: $reference);
            if ($amount === array_sum(array_column($remaining, 'amountMinor')) && 'posted' === $gift->status) {
                $this->records->save($gift->reverse());
            }
            foreach ($this->records->all('receipts') as $receipt) {
                if ($receipt->giftId === $giftId && !$receipt->void) {
                    $this->records->save($receipt->voidReceipt());
                }
            }
            // Any refund invalidates explicit pledge credits; staff may reassign the remaining gift.
            foreach ($this->records->all('pledge_allocations') as $link) {
                if ($link->giftId === $giftId && 'active' === $link->status) {
                    $this->records->save($link->cancel('Online refund changed the available gift; review and reassign its remaining amount.'));
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

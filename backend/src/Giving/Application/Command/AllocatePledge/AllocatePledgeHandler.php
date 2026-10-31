<?php

declare(strict_types=1);

namespace App\Giving\Application\Command\AllocatePledge;

use App\Giving\Domain\Model\PledgeAllocation;
use App\Giving\Domain\Repository\GivingRepository;
use App\Shared\Application\WorkflowTransaction;
use DomainException;
use OutOfBoundsException;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

final readonly class AllocatePledgeHandler
{
    public function __construct(private GivingRepository $records, private WorkflowTransaction $transactions)
    {
    }

    #[AsMessageHandler]
    public function __invoke(AllocatePledgeCommand $message): object
    {
        $pledgeId = $message->pledgeId;
        $giftId = $message->giftId;
        $amountMinor = $message->amountMinor;
        $reference = $message->reference;

        return $this->transactions->run(function () use ($pledgeId, $giftId, $amountMinor, $reference) {
            $pledge = $this->required('pledges', $pledgeId);
            $gift = $this->required('gifts', $giftId);
            $candidate = new PledgeAllocation($pledgeId, $giftId, $amountMinor, $pledge->currency, $reference);
            $usedGift = 0;
            $usedPledge = 0;
            foreach ($this->records->all('pledge_allocations') as $link) {
                if ($link->reference === $reference) {
                    if ($link->pledgeId !== $pledgeId || $link->giftId !== $giftId || $link->amountMinor !== $amountMinor) {
                        throw new DomainException('This reference was used for another pledge allocation.');
                    }

                    return $link;
                }
                if ('active' !== $link->status) {
                    continue;
                }
                $linkedPledge = $this->required('pledges', $link->pledgeId);
                if ($link->giftId === $giftId && $linkedPledge->fundId === $pledge->fundId) {
                    $usedGift += $link->amountMinor;
                }
                if ($link->pledgeId === $pledgeId && 'posted' === $this->required('gifts', $link->giftId)->status) {
                    $usedPledge += $link->amountMinor;
                }
            }
            if ('open' !== $pledge->status || 'posted' !== $gift->status || $gift->donorId !== $pledge->donorId || $gift->currency !== $pledge->currency) {
                throw new DomainException('Choose an open pledge and posted gift from the same donor and currency.');
            }
            $fundAmount = array_sum(array_column(array_filter($gift->allocations, fn ($a) => $a['fundId'] === $pledge->fundId), 'amountMinor'));
            foreach ($this->records->all('reversals') as $refund) {
                if ($refund->giftId === $giftId) {
                    foreach ($refund->allocations as $allocation) {
                        if ($allocation['fundId'] === $pledge->fundId) {
                            $fundAmount -= $allocation['amountMinor'];
                        }
                    }
                }
            }
            if ($usedGift + $amountMinor > $fundAmount || $usedPledge + $amountMinor > $pledge->amountMinor) {
                throw new DomainException('Allocation exceeds available gift or pledge amount.');
            }
            $this->records->save($candidate);

            return $this->required('pledge_allocations', $candidate->id);
        });
    }

    private function required(string $kind, string $id): object
    {
        return $this->records->find($kind, $id) ?? throw new OutOfBoundsException('Record not found.');
    }
}

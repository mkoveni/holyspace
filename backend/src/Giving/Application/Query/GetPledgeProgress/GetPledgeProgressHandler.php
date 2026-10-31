<?php

declare(strict_types=1);

namespace App\Giving\Application\Query\GetPledgeProgress;

use App\Giving\Application\ReadModel\GivingReadStore;
use App\People\Application\ReadModel\PersonDirectory;
use App\Shared\Domain\ValueObject\Input;

final readonly class GetPledgeProgressHandler
{
    public function __construct(private GivingReadStore $records, private PersonDirectory $people)
    {
    }

    public function __invoke(GetPledgeProgressQuery $message): array
    {
        $asOf = $message->asOf;
        Input::date($asOf);
        $gifts = array_column($this->records->all('gifts'), null, 'id');
        $reversed = [];
        foreach ($this->records->all('reversals') as $r) {
            if ($r->reversedOn <= $asOf) {
                $reversed[$r->giftId] = true;
            }
        }
        $links = $this->records->all('pledge_allocations');
        $rows = [];
        $pledges = $this->records->all('pledges');
        $people = $this->people->getMany(array_column($pledges, 'donorId'));
        foreach ($pledges as $pledge) {
            $applied = 0;
            foreach ($links as $link) {
                if ('active' === $link->status && $link->pledgeId === $pledge->id && isset($gifts[$link->giftId]) && $gifts[$link->giftId]->receivedOn <= $asOf && ('posted' === $gifts[$link->giftId]->status || !isset($reversed[$link->giftId]))) {
                    $applied += $link->amountMinor;
                }
            }
            $remaining = max(0, $pledge->amountMinor - $applied);
            $rows[] = ['id' => $pledge->id, 'donorId' => $pledge->donorId, 'donorName' => $people[$pledge->donorId]['name'] ?? 'Person unavailable', 'fundId' => $pledge->fundId, 'currency' => $pledge->currency, 'amountMinor' => $pledge->amountMinor, 'appliedAmountMinor' => $applied, 'remainingAmountMinor' => $remaining, 'dueOn' => $pledge->dueOn, 'status' => $pledge->status, 'overdue' => 'open' === $pledge->status && $remaining > 0 && $pledge->dueOn < $asOf];
        }

        return ['asOf' => $asOf, 'items' => $rows, 'allocations' => $links, 'notice' => 'Progress uses explicit gift assignments and gift/reversal dates, using currently recorded assignments and pledge statuses. It is not a historical audit snapshot. No pledge status changes automatically.'];
    }
}

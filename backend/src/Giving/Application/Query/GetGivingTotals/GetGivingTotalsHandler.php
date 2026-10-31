<?php

declare(strict_types=1);

namespace App\Giving\Application\Query\GetGivingTotals;

use App\Giving\Application\ReadModel\GivingReadStore;
use App\Shared\Domain\ValueObject\Input;
use InvalidArgumentException;
use OutOfBoundsException;

final readonly class GetGivingTotalsHandler
{
    public function __construct(private GivingReadStore $records)
    {
    }

    public function __invoke(GetGivingTotalsQuery $message): array
    {
        $from = $message->from;
        $to = $message->to;
        $fundId = $message->fundId;
        if (null !== $from) {
            Input::date($from);
        }
        if (null !== $to) {
            Input::date($to);
        }
        if (null !== $from && null !== $to && $from > $to) {
            throw new InvalidArgumentException('Period end must not precede start.');
        }
        if (null !== $fundId) {
            $this->required('funds', $fundId);
        }
        $groups = [];
        foreach (['gifts', 'reversals'] as $kind) {
            foreach ($this->records->all($kind) as $record) {
                $date = 'gifts' === $kind ? $record->receivedOn : $record->reversedOn;
                if (null !== $from && $date < $from || null !== $to && $date > $to) {
                    continue;
                }
                foreach ($record->allocations as $allocation) {
                    if (null !== $fundId && $allocation['fundId'] !== $fundId) {
                        continue;
                    }
                    $key = $record->currency.':'.$allocation['fundId'];
                    $groups[$key] ??= ['id' => $key, 'fundId' => $allocation['fundId'], 'currency' => $record->currency, 'grossAmountMinor' => 0, 'reversalAmountMinor' => 0, 'netAmountMinor' => 0];
                    $groups[$key]['gifts' === $kind ? 'grossAmountMinor' : 'reversalAmountMinor'] += $allocation['amountMinor'];
                }
            }
        }
        foreach ($groups as &$group) {
            $group['netAmountMinor'] = $group['grossAmountMinor'] - $group['reversalAmountMinor'];
        }

        return array_values($groups);
    }

    private function required(string $kind, string $id): object
    {
        return $this->records->find($kind, $id) ?? throw new OutOfBoundsException('Record not found.');
    }
}

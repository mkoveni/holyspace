<?php

declare(strict_types=1);

namespace App\Giving\Application\Query\GetGivingReport;

use App\Giving\Application\ReadModel\GivingReadStore;
use App\People\Application\ReadModel\PersonDirectory;
use App\Shared\Domain\ValueObject\Input;
use DomainException;
use InvalidArgumentException;
use OutOfBoundsException;

final readonly class GetGivingReportHandler
{
    public function __construct(private GivingReadStore $records, private PersonDirectory $people)
    {
    }

    public function __invoke(GetGivingReportQuery $message): array
    {
        $from = $message->from;
        $to = $message->to;
        $donorId = $message->donorId;
        $fundId = $message->fundId;
        $start = Input::date($from);
        $end = Input::date($to);
        if ($end < $start || $start->diff($end)->days > 366) {
            throw new InvalidArgumentException('Choose an ordered period of at most 367 days.');
        }
        $donor = null;
        if (null !== $donorId) {
            $person = $this->people->get($donorId) ?? throw new OutOfBoundsException('Donor not found.');
            $donor = ['id' => $person['id'], 'name' => $person['name']];
        }
        $funds = array_column($this->records->all('funds'), null, 'id');
        if (null !== $fundId && !isset($funds[$fundId])) {
            throw new OutOfBoundsException('Fund not found.');
        }
        $gifts = array_column($this->records->all('gifts'), null, 'id');
        $people = $this->people->getMany(array_values(array_filter(array_column($gifts, 'donorId'), static fn ($id) => null !== $id)));
        $entries = [];
        $totals = [];
        $fundTotals = [];
        foreach (['gifts', 'reversals'] as $kind) {
            foreach ('gifts' === $kind ? $gifts : $this->records->all('reversals') as $record) {
                $gift = 'gifts' === $kind ? $record : $gifts[$record->giftId] ?? throw new DomainException('A reversal has no original gift.');
                $date = 'gifts' === $kind ? $record->receivedOn : $record->reversedOn;
                if ($date < $from || $date > $to || null !== $donorId && $gift->donorId !== $donorId) {
                    continue;
                }
                $name = null === $gift->donorId ? 'Anonymous' : $people[$gift->donorId]['name'] ?? 'Person unavailable';
                foreach ($record->allocations as $allocation) {
                    if (null !== $fundId && $allocation['fundId'] !== $fundId) {
                        continue;
                    }
                    $amount = $allocation['amountMinor'];
                    $currency = $record->currency;
                    $entry = ['id' => $record->id.':'.$allocation['fundId'], 'date' => $date, 'type' => 'gifts' === $kind ? 'gift' : 'reversal', 'giftId' => $gift->id, 'donorId' => $gift->donorId, 'donorName' => $name, 'fundId' => $allocation['fundId'], 'fundName' => $funds[$allocation['fundId']]->name ?? 'Fund unavailable', 'currency' => $currency, 'amountMinor' => 'gifts' === $kind ? $amount : -$amount, 'method' => $gift->method];
                    $entries[] = $entry;
                    $totals[$currency] ??= ['currency' => $currency, 'grossAmountMinor' => 0, 'reversalAmountMinor' => 0, 'netAmountMinor' => 0];
                    $key = $currency.':'.$allocation['fundId'];
                    $fundTotals[$key] ??= ['fundId' => $allocation['fundId'], 'fundName' => $entry['fundName'], 'currency' => $currency, 'grossAmountMinor' => 0, 'reversalAmountMinor' => 0, 'netAmountMinor' => 0];
                    $column = 'gifts' === $kind ? 'grossAmountMinor' : 'reversalAmountMinor';
                    $totals[$currency][$column] += $amount;
                    $fundTotals[$key][$column] += $amount;
                }
            }
        }
        foreach ($totals as &$total) {
            $total['netAmountMinor'] = $total['grossAmountMinor'] - $total['reversalAmountMinor'];
        }
        unset($total);
        foreach ($fundTotals as &$total) {
            $total['netAmountMinor'] = $total['grossAmountMinor'] - $total['reversalAmountMinor'];
        }
        unset($total);
        usort($entries, fn ($a, $b) => [$a['date'], $a['id']] <=> [$b['date'], $b['id']]);
        ksort($totals);
        ksort($fundTotals);

        return ['from' => $from, 'to' => $to, 'donor' => $donor, 'fundId' => $fundId, 'entries' => $entries, 'totals' => array_values($totals), 'fundTotals' => array_values($fundTotals), 'notice' => 'Donation acknowledgement. This statement makes no claim of tax deductibility. Reversals are recorded on their reversal date.'];
    }
}

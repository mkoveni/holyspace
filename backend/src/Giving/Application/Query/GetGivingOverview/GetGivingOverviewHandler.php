<?php

declare(strict_types=1);

namespace App\Giving\Application\Query\GetGivingOverview;

use App\Giving\Application\Query\GetGivingTotals\GetGivingTotalsHandler;
use App\Giving\Application\Query\GetGivingTotals\GetGivingTotalsQuery;
use App\Giving\Application\ReadModel\GivingReadStore;

final readonly class GetGivingOverviewHandler
{
    public function __construct(private GivingReadStore $records, private GetGivingTotalsHandler $getGivingTotalsHandler)
    {
    }

    public function __invoke(GetGivingOverviewQuery $message): array
    {
        $from = $message->from;
        $to = $message->to;
        $fundId = $message->fundId;
        $result = [];
        foreach (['funds', 'gifts', 'receipts', 'reversals', 'pledges', 'batches'] as $kind) {
            $result[$kind] = $this->records->all($kind);
        }
        $result['totals'] = ($this->getGivingTotalsHandler)(new GetGivingTotalsQuery($from, $to, $fundId));

        return $result;
    }
}

<?php

declare(strict_types=1);

namespace App\Giving\Application\Query\GetPledge;

use App\Giving\Application\Contract\PledgeDetail;
use App\Giving\Application\Contract\PledgeReader;
use App\Giving\Application\GivingAccess;
use App\Giving\Domain\GivingException;
use App\Giving\Domain\GivingRules;

final readonly class GetPledgeHandler
{
    public function __construct(
        private GivingAccess $access,
        private PledgeReader $reader,
    ) {
    }

    public function handle(GetPledgeQuery $query): PledgeDetail
    {
        $this->access->read();
        GivingRules::page($query->limit, $query->offset);

        return $this->reader->detail($query->pledgeId, $query->limit, $query->offset) ?? throw GivingException::missing();
    }
}

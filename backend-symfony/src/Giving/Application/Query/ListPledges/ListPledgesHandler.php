<?php

declare(strict_types=1);

namespace App\Giving\Application\Query\ListPledges;

use App\Giving\Application\Contract\PledgePage;
use App\Giving\Application\Contract\PledgeReader;
use App\Giving\Application\GivingAccess;
use App\Giving\Domain\GivingException;
use App\Giving\Domain\GivingRules;
use App\Giving\Domain\Pledge;

final readonly class ListPledgesHandler
{
    public function __construct(
        private GivingAccess $access,
        private PledgeReader $reader,
    ) {
    }

    public function handle(ListPledgesQuery $query): PledgePage
    {
        $this->access->read();
        GivingRules::page($query->limit, $query->offset);
        if (null !== $query->status && !\in_array($query->status, Pledge::STATUSES, true)) {
            throw GivingException::invalid('Choose open or cancelled pledges.');
        }

        return $this->reader->list($query->donorId, $query->status, $query->limit, $query->offset);
    }
}

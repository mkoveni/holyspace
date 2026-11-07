<?php

declare(strict_types=1);

namespace App\Giving\Application\Query\ListPledgeGiftCandidates;

use App\Giving\Application\Contract\PledgeCandidate;
use App\Giving\Application\Contract\PledgeReader;
use App\Giving\Application\GivingAccess;
use App\Giving\Domain\GivingException;
use App\Giving\Domain\GivingRules;

final readonly class ListPledgeGiftCandidatesHandler
{
    public function __construct(
        private GivingAccess $access,
        private PledgeReader $reader,
    ) {
    }

    /** @return list<PledgeCandidate> */
    public function handle(ListPledgeGiftCandidatesQuery $query): array
    {
        $this->access->read();
        GivingRules::page($query->limit, $query->offset);

        return $this->reader->candidates($query->pledgeId, $query->limit, $query->offset) ?? throw GivingException::missing();
    }
}

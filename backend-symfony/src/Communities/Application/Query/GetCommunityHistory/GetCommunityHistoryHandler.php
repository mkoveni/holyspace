<?php

declare(strict_types=1);

namespace App\Communities\Application\Query\GetCommunityHistory;

use App\Communities\Application\CommunityAccess;
use App\Communities\Application\Contract\CommunityReader;
use App\Communities\Application\Contract\HistoryEntry;
use App\Communities\Domain\CommunityRules;

final readonly class GetCommunityHistoryHandler
{
    public function __construct(private CommunityAccess $access, private CommunityReader $reader)
    {
    }

    /** @return list<HistoryEntry> */
    public function handle(GetCommunityHistoryQuery $query): array
    {
        $this->access->directoryScope();
        $this->access->require($query->communityId);
        CommunityRules::page($query->limit, $query->offset);

        return $this->reader->history($query->communityId, $query->limit, $query->offset);
    }
}

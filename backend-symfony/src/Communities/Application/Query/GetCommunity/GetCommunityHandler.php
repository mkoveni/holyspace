<?php

declare(strict_types=1);

namespace App\Communities\Application\Query\GetCommunity;

use App\Communities\Application\CommunityAccess;
use App\Communities\Application\Contract\CommunityDetails;

/** Directory readers see any community's definition; leaders only those they lead. */
final readonly class GetCommunityHandler
{
    public function __construct(private CommunityAccess $access)
    {
    }

    public function handle(GetCommunityQuery $query): CommunityDetails
    {
        $this->access->directoryScope();
        if (null !== $this->access->directoryScope()) {
            $this->access->require($query->communityId);
        }

        return new CommunityDetails($this->access->community($query->communityId), $this->access->canOperate($query->communityId));
    }
}

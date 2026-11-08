<?php

declare(strict_types=1);

namespace App\Communities\Application\Query\ListCommunityDeliveries;

use App\Communications\Application\Contract\DeliveryReader;
use App\Communications\Application\Contract\DeliverySummary;
use App\Communities\Application\CommunityAccess;
use App\Communities\Application\Contract\NoticeReader;
use App\Communities\Domain\CommunityRules;
use App\IdentityAccess\Application\Contract\PermissionChecker;

/** Delivery outcomes of a community's notices, newest notice first; no addresses or content. */
final readonly class ListCommunityDeliveriesHandler
{
    public function __construct(
        private PermissionChecker $auth,
        private CommunityAccess $access,
        private NoticeReader $notices,
        private DeliveryReader $deliveries,
    ) {
    }

    /** @return list<DeliverySummary> */
    public function handle(ListCommunityDeliveriesQuery $query): array
    {
        $this->auth->requirePermission(CommunityAccess::SEND);
        $this->access->require($query->communityId);
        CommunityRules::page($query->limit, $query->offset);
        $ids = $this->notices->noticeIds($query->communityId, $query->limit, $query->offset);
        $summaries = [] === $ids ? [] : $this->deliveries->summaries($ids);
        $result = [];
        foreach ($ids as $id) {
            if (isset($summaries[$id])) {
                $result[] = $summaries[$id];
            }
        }

        return $result;
    }
}

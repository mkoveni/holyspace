<?php

declare(strict_types=1);

namespace App\ChildrenMinistry\Safeguarding\Application\Query\GetSafeguardingFollowUp;

use App\ChildrenMinistry\Safeguarding\Application\Contract\FollowUpView;
use App\ChildrenMinistry\Safeguarding\Application\Contract\SafeguardingFollowUps;
use App\IdentityAccess\Application\Contract\PermissionChecker;

/** Read-only. Care review dates are included only for callers who may see care records, and filtering by care needs that permission. */
final readonly class GetSafeguardingFollowUpHandler
{
    public function __construct(private PermissionChecker $authorization, private SafeguardingFollowUps $followUp)
    {
    }

    public function handle(GetSafeguardingFollowUpQuery $query): FollowUpView
    {
        $this->authorization->requirePermission('children.safeguarding');
        $care = $this->authorization->hasPermission('children.support');
        if ('CARE' === $query->kind) {
            $this->authorization->requirePermission('children.support');
        }

        return $this->followUp->list($query->programmeId, $query->search, $query->kind, $query->state, $query->days, $query->limit, $query->offset, $care);
    }
}

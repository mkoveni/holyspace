<?php

declare(strict_types=1);

namespace App\ChildrenMinistry\Safeguarding\Application\Query\GetSafeguarding;

use App\ChildrenMinistry\Safeguarding\Application\Contract\SafeguardingReader;
use App\ChildrenMinistry\Safeguarding\Application\SafeguardingView;
use App\IdentityAccess\Application\Contract\PermissionChecker;

final readonly class GetSafeguardingHandler
{
    public function __construct(private PermissionChecker $authorization, private SafeguardingReader $safeguarding)
    {
    }

    public function handle(GetSafeguardingQuery $query): SafeguardingView
    {
        $this->authorization->requirePermission('children.safeguarding');

        return new SafeguardingView($this->safeguarding->authorities($query->childId), $this->safeguarding->consents($query->childId), $this->safeguarding->authorizations($query->childId));
    }
}

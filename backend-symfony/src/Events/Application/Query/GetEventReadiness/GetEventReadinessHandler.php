<?php

declare(strict_types=1);

namespace App\Events\Application\Query\GetEventReadiness;

use App\Events\Application\Contract\ReadinessView;
use App\Events\Application\EventReadiness;
use App\IdentityAccess\Application\Contract\PermissionChecker;

final readonly class GetEventReadinessHandler
{
    public function __construct(private PermissionChecker $authorization, private EventReadiness $readiness)
    {
    }

    public function handle(GetEventReadinessQuery $query): ReadinessView
    {
        $this->authorization->requirePermission('events.manage');

        return $this->readiness->view($query->occurrenceId);
    }
}

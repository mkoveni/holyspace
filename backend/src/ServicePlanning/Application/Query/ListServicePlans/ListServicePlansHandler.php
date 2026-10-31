<?php

declare(strict_types=1);

namespace App\ServicePlanning\Application\Query\ListServicePlans;

use App\ServicePlanning\Application\ReadModel\ServicePlanningReadStore;
use App\Shared\Application\Exception\AccessDenied;
use App\Shared\Application\Security\AccessControl;

final readonly class ListServicePlansHandler
{
    public function __construct(private ServicePlanningReadStore $records, private AccessControl $authorization)
    {
    }

    public function __invoke(ListServicePlansQuery $message): array
    {
        if (!$this->authorization->isGranted('service_planning.read')) {
            throw new AccessDenied('Service plan read access is required.');
        }

        return ['items' => $this->records->all('plans')];
    }
}

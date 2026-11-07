<?php

declare(strict_types=1);

namespace App\ChildrenMinistry\Participation\Application\Query\ListClasses;

use App\ChildrenMinistry\Participation\Application\ClassView;
use App\ChildrenMinistry\Participation\Application\Contract\ParticipationReader;
use App\IdentityAccess\Application\Contract\PermissionChecker;

final readonly class ListClassesHandler
{
    public function __construct(
        private PermissionChecker $authorization,
        private ParticipationReader $participation,
    ) {
    }

    /** @return list<ClassView> */
    public function handle(ListClassesQuery $query): array
    {
        $this->authorization->requirePermission('children.read');

        return $this->participation->classes($query->programmeId);
    }
}

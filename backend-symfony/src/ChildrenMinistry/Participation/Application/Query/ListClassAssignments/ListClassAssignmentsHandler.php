<?php

declare(strict_types=1);

namespace App\ChildrenMinistry\Participation\Application\Query\ListClassAssignments;

use App\ChildrenMinistry\Participation\Application\AssignmentView;
use App\ChildrenMinistry\Participation\Application\Contract\ParticipationReader;
use App\ChildrenMinistry\Shared\Domain\ChildrenRules;
use App\IdentityAccess\Application\Contract\PermissionChecker;

final readonly class ListClassAssignmentsHandler
{
    public function __construct(
        private PermissionChecker $authorization,
        private ParticipationReader $participation,
    ) {
    }

    /** @return list<AssignmentView> */
    public function handle(ListClassAssignmentsQuery $query): array
    {
        $this->authorization->requirePermission('children.read');
        ChildrenRules::page($query->limit, $query->offset);

        return $this->participation->assignments($query->childId, $query->limit, $query->offset);
    }
}

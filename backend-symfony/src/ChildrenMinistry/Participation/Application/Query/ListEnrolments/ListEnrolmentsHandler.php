<?php

declare(strict_types=1);

namespace App\ChildrenMinistry\Participation\Application\Query\ListEnrolments;

use App\ChildrenMinistry\Participation\Application\Contract\ParticipationReader;
use App\ChildrenMinistry\Participation\Application\EnrolmentView;
use App\ChildrenMinistry\Shared\Domain\ChildrenRules;
use App\IdentityAccess\Application\Contract\PermissionChecker;

final readonly class ListEnrolmentsHandler
{
    public function __construct(
        private PermissionChecker $authorization,
        private ParticipationReader $participation,
    ) {
    }

    /** @return list<EnrolmentView> */
    public function handle(ListEnrolmentsQuery $query): array
    {
        $this->authorization->requirePermission('children.read');
        ChildrenRules::page($query->limit, $query->offset);

        return $this->participation->enrolments($query->childId, $query->limit, $query->offset);
    }
}

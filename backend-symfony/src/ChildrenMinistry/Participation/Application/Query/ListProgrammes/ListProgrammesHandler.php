<?php

declare(strict_types=1);

namespace App\ChildrenMinistry\Participation\Application\Query\ListProgrammes;

use App\ChildrenMinistry\Participation\Application\Contract\ParticipationReader;
use App\ChildrenMinistry\Participation\Application\ProgrammeView;
use App\ChildrenMinistry\Shared\Domain\ChildrenRules;
use App\IdentityAccess\Application\Contract\PermissionChecker;

final readonly class ListProgrammesHandler
{
    public function __construct(
        private PermissionChecker $authorization,
        private ParticipationReader $participation,
    ) {
    }

    /** @return list<ProgrammeView> */
    public function handle(ListProgrammesQuery $query): array
    {
        $this->authorization->requirePermission('children.read');
        ChildrenRules::page($query->limit, $query->offset);

        return $this->participation->programmes(ChildrenRules::optional($query->search, 120), $query->limit, $query->offset);
    }
}

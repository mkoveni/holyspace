<?php

declare(strict_types=1);

namespace App\ChildrenMinistry\Participation\Application\Query\GetProgramme;

use App\ChildrenMinistry\Participation\Application\Contract\ParticipationReader;
use App\ChildrenMinistry\Participation\Application\ProgrammeView;
use App\ChildrenMinistry\Shared\Domain\ChildrenException;
use App\IdentityAccess\Application\Contract\PermissionChecker;

final readonly class GetProgrammeHandler
{
    public function __construct(
        private PermissionChecker $authorization,
        private ParticipationReader $participation,
    ) {
    }

    public function handle(GetProgrammeQuery $query): ProgrammeView
    {
        $this->authorization->requirePermission('children.read');

        return $this->participation->programme($query->programmeId) ?? throw ChildrenException::missing('Programme');
    }
}

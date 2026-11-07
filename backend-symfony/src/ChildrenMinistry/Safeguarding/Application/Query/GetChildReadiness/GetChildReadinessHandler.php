<?php

declare(strict_types=1);

namespace App\ChildrenMinistry\Safeguarding\Application\Query\GetChildReadiness;

use App\ChildrenMinistry\Participation\Application\Contract\ParticipationReader;
use App\ChildrenMinistry\Safeguarding\Application\Readiness\ReadinessAssessor;
use App\ChildrenMinistry\Safeguarding\Application\Readiness\ReadinessView;
use App\ChildrenMinistry\Shared\Domain\ChildrenException;
use App\IdentityAccess\Application\Contract\PermissionChecker;
use App\People\Application\Contract\PersonDirectory;

final readonly class GetChildReadinessHandler
{
    public function __construct(
        private PermissionChecker $authorization,
        private ParticipationReader $participation,
        private PersonDirectory $people,
        private ReadinessAssessor $readiness,
    ) {
    }

    public function handle(GetChildReadinessQuery $query): ReadinessView
    {
        $this->authorization->requirePermission('children.read');
        $programme = $this->participation->programme($query->programmeId) ?? throw ChildrenException::missing('Programme');
        if (null === $this->people->get($query->childId)) {
            throw ChildrenException::missing('Person');
        }

        return $this->readiness->assess($programme->id, $programme->currentPolicyId, [$query->childId])[$query->childId];
    }
}

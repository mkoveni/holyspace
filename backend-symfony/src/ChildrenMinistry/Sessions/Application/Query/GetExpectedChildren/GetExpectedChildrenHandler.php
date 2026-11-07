<?php

declare(strict_types=1);

namespace App\ChildrenMinistry\Sessions\Application\Query\GetExpectedChildren;

use App\ChildrenMinistry\Participation\Application\Contract\ParticipationReader;
use App\ChildrenMinistry\Sessions\Application\Contract\SessionReader;
use App\ChildrenMinistry\Sessions\Application\ExpectedChild;
use App\ChildrenMinistry\Sessions\Application\SessionOperations;
use App\ChildrenMinistry\Sessions\Application\StaffScope;
use App\ChildrenMinistry\Shared\Application\ParticipantAccess;
use App\ChildrenMinistry\Shared\Domain\ChildrenException;
use App\ChildrenMinistry\Shared\Domain\ChildrenRules;
use App\IdentityAccess\Application\Contract\PermissionChecker;

/** Placed children for the session's date. Enrolment does not reserve capacity. */
final readonly class GetExpectedChildrenHandler
{
    public function __construct(
        private PermissionChecker $authorization,
        private StaffScope $scope,
        private SessionReader $sessions,
        private ParticipationReader $participation,
        private SessionOperations $operations,
        private ParticipantAccess $people,
    ) {
    }

    /** @return list<ExpectedChild> */
    public function handle(GetExpectedChildrenQuery $query): array
    {
        $this->authorization->requireActorPermission($query->actorId, 'children.station');
        $this->scope->require($query->actorId, $query->sessionId);
        ChildrenRules::page($query->limit, $query->offset);
        $session = $this->sessions->ministrySession($query->sessionId) ?? throw ChildrenException::missing('Session');

        return array_map(
            fn ($assignment): ExpectedChild => new ExpectedChild($assignment->childId, $this->people->person($assignment->childId)->name, $assignment->enrolmentId, $assignment->id),
            $this->participation->expectedChildren($session->classId, $this->operations->date($session), $query->limit, $query->offset),
        );
    }
}

<?php

declare(strict_types=1);

namespace App\ChildrenMinistry\Sessions\Application\Query\GetStationSession;

use App\ChildrenMinistry\Sessions\Application\Contract\SessionReader;
use App\ChildrenMinistry\Sessions\Application\SessionOperations;
use App\ChildrenMinistry\Sessions\Application\SessionView;
use App\ChildrenMinistry\Sessions\Application\StaffScope;
use App\ChildrenMinistry\Shared\Domain\ChildrenException;
use App\IdentityAccess\Application\Contract\PermissionChecker;
use Psr\Clock\ClockInterface;

final readonly class GetStationSessionHandler
{
    public function __construct(
        private PermissionChecker $authorization,
        private SessionReader $sessions,
        private SessionOperations $operations,
        private StaffScope $scope,
        private ClockInterface $clock,
    ) {
    }

    public function handle(GetStationSessionQuery $query): SessionView
    {
        $this->authorization->requireActorPermission($query->actorId, 'children.station');
        $this->scope->require($query->actorId, $query->sessionId);

        return $this->operations->view($this->sessions->ministrySession($query->sessionId) ?? throw ChildrenException::missing('Session'), $this->clock->now());
    }
}

<?php

declare(strict_types=1);

namespace App\ChildrenMinistry\Sessions\Application\Query\GetFamilyArrivalReference;

use App\ChildrenMinistry\Participation\Application\Contract\ParticipationReader;
use App\ChildrenMinistry\Sessions\Application\Contract\SessionReader;
use App\ChildrenMinistry\Sessions\Application\FamilyArrivalReview;
use App\ChildrenMinistry\Sessions\Application\FamilyChild;
use App\ChildrenMinistry\Sessions\Application\SessionChildPreparation;
use App\ChildrenMinistry\Sessions\Application\SessionOperations;
use App\ChildrenMinistry\Sessions\Application\StaffScope;
use App\ChildrenMinistry\Shared\Domain\ChildrenException;
use App\IdentityAccess\Application\Contract\PermissionChecker;
use App\People\Application\Contract\PersonDirectory;
use Psr\Clock\ClockInterface;

/**
 * Station staff review what a family's QR points at. The QR carries identifiers, never authority: the read is scoped by
 * the child's current dated placement and enrolment, and it records nothing.
 */
final readonly class GetFamilyArrivalReferenceHandler
{
    public function __construct(
        private PermissionChecker $authorization,
        private StaffScope $scope,
        private SessionReader $sessions,
        private ParticipationReader $participation,
        private PersonDirectory $people,
        private SessionChildPreparation $preparation,
        private SessionOperations $operations,
        private ClockInterface $clock,
    ) {
    }

    public function handle(GetFamilyArrivalReferenceQuery $query): FamilyArrivalReview
    {
        $this->authorization->requireActorPermission($query->actorId, 'children.station');
        $this->scope->require($query->actorId, $query->sessionId);
        $session = $this->sessions->ministrySession($query->sessionId) ?? throw ChildrenException::missing('Session');
        $date = $this->operations->date($session);
        $placement = $this->participation->assignmentAt($session->classId, $query->childId, $date) ?? throw ChildrenException::missing('Expected child');
        $enrolment = $this->participation->enrolmentAt($placement->programmeId, $query->childId, $date);
        if (null === $enrolment || $enrolment->id !== $placement->enrolmentId) {
            throw ChildrenException::missing('Expected child');
        }
        $child = $this->people->get($query->childId);
        if (null === $child || !$child->available) {
            throw ChildrenException::missing('Expected child');
        }

        return new FamilyArrivalReview(new FamilyChild($child->id, $child->name), $this->preparation->issues($session, [$child->id => $child], $this->clock->now())[$child->id] ?? []);
    }
}

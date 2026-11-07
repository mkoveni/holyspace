<?php

declare(strict_types=1);

namespace App\ChildrenMinistry\Sessions\Application;

use App\ChildrenMinistry\Participation\Application\Contract\ParticipationReader;
use App\ChildrenMinistry\Safeguarding\Application\Contract\SupportSessionScope;
use App\ChildrenMinistry\Sessions\Application\Contract\AttendanceReader;
use App\ChildrenMinistry\Sessions\Application\Contract\SessionReader;
use App\ChildrenMinistry\Shared\Domain\ChildrenException;

/**
 * A staff member may open a child's support record from a session only if they are present on that session and the
 * child is placed in its class on the session's date, or is currently checked in to it.
 */
final readonly class SessionSupportScope implements SupportSessionScope
{
    public function __construct(
        private StaffScope $scope,
        private SessionReader $sessions,
        private ParticipationReader $participation,
        private AttendanceReader $attendance,
        private SessionOperations $operations,
    ) {
    }

    public function require(string $actorId, string $sessionId, string $childId): void
    {
        $this->scope->require($actorId, $sessionId);
        $session = $this->sessions->ministrySession($sessionId) ?? throw ChildrenException::missing('MinistrySession');
        $date = $this->operations->date($session);
        $assignment = $this->participation->assignmentAt($session->classId, $childId, $date);
        $enrolment = null === $assignment ? null : $this->participation->enrolment($assignment->enrolmentId);
        $enrolled = null !== $enrolment && $enrolment->covers($date);
        if (!$enrolled && !$this->attendance->checkedIn($childId, $session->id)) {
            throw ChildrenException::forbidden();
        }
    }
}

<?php

declare(strict_types=1);

namespace App\ChildrenMinistry\Sessions\Application\Query\ListServingCandidates;

use App\ChildrenMinistry\Sessions\Application\Contract\SessionReader;
use App\ChildrenMinistry\Sessions\Application\ServingCandidate;
use App\ChildrenMinistry\Shared\Domain\ChildrenException;
use App\IdentityAccess\Application\Contract\PermissionChecker;
use App\Volunteering\Application\Contract\StaffingAssessment;
use App\Volunteering\Application\Contract\StaffingDirectory;
use App\Volunteering\Application\Contract\StaffingUnavailable;

final readonly class ListServingCandidatesHandler
{
    public function __construct(private PermissionChecker $authorization, private SessionReader $sessions, private StaffingDirectory $staffing)
    {
    }

    /** @return list<ServingCandidate> */
    public function handle(ListServingCandidatesQuery $query): array
    {
        $this->authorization->requireActorPermission($query->actorId, 'children.manage');
        $session = $this->sessions->ministrySession($query->sessionId) ?? throw ChildrenException::missing('Session');
        try {
            $assessments = $this->staffing->candidates($session->id, $session->startsAt, $session->endsAt);
        } catch (StaffingUnavailable) {
            throw ChildrenException::conflict('Serving assignments are unavailable or exceed the review limit. Review the serving schedule.');
        }

        return array_values(array_map(
            fn (StaffingAssessment $assessment): ServingCandidate => new ServingCandidate($assessment, null !== $this->sessions->worker($assessment->personId)),
            array_filter($assessments, static fn (StaffingAssessment $assessment): bool => in_array($assessment->responsibility, ['LEADER', 'ASSISTANT'], true)),
        ));
    }
}

<?php

declare(strict_types=1);

namespace App\ChildrenMinistry\Sessions\Application;

use App\ChildrenMinistry\Participation\Application\Contract\ParticipationReader;
use App\ChildrenMinistry\Participation\Domain\MinistryClass;
use App\ChildrenMinistry\Safeguarding\Application\Contract\SafeguardingReader;
use App\ChildrenMinistry\Sessions\Domain\MinistrySession;
use App\ChildrenMinistry\Shared\Application\ParticipantAccess;
use App\ChildrenMinistry\Shared\Domain\ChildrenException;
use App\ChildrenMinistry\Shared\Domain\ChildrenRules;
use DateTimeImmutable;

/**
 * Whether a child may be in a session now. Age, a programme enrolment and a class placement are judged on the
 * session's local day; consent for the programme's current policy must cover [now, end of session] under an
 * authority that also covers it. Returns the evidence a segment records.
 */
final readonly class AdmissionPolicy
{
    public function __construct(
        private ParticipationReader $participation,
        private SafeguardingReader $safeguarding,
        private ParticipantAccess $participants,
        private SessionOperations $sessions,
    ) {
    }

    public function require(MinistrySession $session, string $childId, DateTimeImmutable $now): AdmissionEvidence
    {
        $group = $this->participation->ministryClass($session->classId) ?? throw ChildrenException::missing('Class');
        $programme = $this->participation->programme($group->programmeId) ?? throw ChildrenException::missing('Programme');
        $on = $this->sessions->date($session);
        $child = $this->participants->available($childId);
        MinistryClass::reconstitute($group->id, $group->programmeId, $group->name, $group->minAgeMonths, $group->maxAgeMonths)->requireAge($child->birthDate, $on);

        $enrolment = $this->participation->enrolmentAt($programme->id, $childId, $on)
            ?? throw ChildrenException::conflict('Active programme enrolment is required for the session date.');
        $assignment = $this->participation->assignmentAt($group->id, $childId, $on);
        if (null === $assignment || $assignment->enrolmentId !== $enrolment->id) {
            throw ChildrenException::conflict('A current class assignment is required for the session date.');
        }

        $consentId = null;
        if (null !== $programme->currentPolicyId) {
            foreach ($this->safeguarding->validConsents($programme->id, $programme->currentPolicyId, $childId, $now, $session->endsAt) as $consent) {
                $authority = $this->safeguarding->guardianAuthority($consent->authorityId);
                if (null !== $authority && null === $authority->revokedAt && ChildrenRules::covers($authority->validFrom, $authority->validUntil, $now, $session->endsAt)) {
                    $consentId = $consent->id;
                    break;
                }
            }
        }

        return new AdmissionEvidence($enrolment->id, $assignment->id, $consentId ?? throw ChildrenException::conflict('Current guardian consent for the programme policy must cover the session.'));
    }
}

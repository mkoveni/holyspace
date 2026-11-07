<?php

declare(strict_types=1);

namespace App\ChildrenMinistry\Sessions\Application;

use App\ChildrenMinistry\Participation\Application\Contract\ParticipationReader;
use App\ChildrenMinistry\Participation\Domain\MinistryClass;
use App\ChildrenMinistry\Safeguarding\Application\Contract\SafeguardingReader;
use App\ChildrenMinistry\Sessions\Domain\MinistrySession;
use App\ChildrenMinistry\Sessions\Domain\SessionStatus;
use App\ChildrenMinistry\Shared\Domain\ChildrenException;
use App\ChildrenMinistry\Shared\Domain\ChildrenRules;
use App\People\Application\Contract\PersonDirectoryEntry;
use DateTimeImmutable;

/**
 * What still stands between each expected child and arrival, for display only: check-in repeats every check under
 * lock. A planned session that has not started is checked from its start, anything else from now.
 */
final readonly class SessionChildPreparation
{
    public function __construct(
        private ParticipationReader $participation,
        private SafeguardingReader $safeguarding,
        private SessionOperations $operations,
    ) {
    }

    /**
     * @param array<string, PersonDirectoryEntry> $children keyed by child id
     *
     * @return array<string, list<string>> issues keyed by child id; empty when the child is ready
     */
    public function issues(MinistrySession $session, array $children, DateTimeImmutable $now): array
    {
        if ([] === $children) {
            return [];
        }
        $group = $this->participation->ministryClass($session->classId) ?? throw ChildrenException::missing('Class');
        $programme = $this->participation->programme($group->programmeId) ?? throw ChildrenException::missing('Programme');
        $from = SessionStatus::Planned === $session->status && $session->startsAt > $now ? $session->startsAt : $now;
        $on = $this->operations->date($session);
        $class = MinistryClass::reconstitute($group->id, $group->programmeId, $group->name, $group->minAgeMonths, $group->maxAgeMonths);

        $result = [];
        foreach ($children as $id => $child) {
            $id = (string) $id;
            $issues = [];
            if (!$child->available) {
                $issues[] = 'Child details need review before arrival.';
            }
            try {
                $class->requireAge($child->birthDate, $on);
            } catch (ChildrenException $exception) {
                $issues[] = $exception->getMessage();
            }
            $enrolment = $this->participation->enrolmentAt($programme->id, $id, $on);
            if (null === $enrolment) {
                $issues[] = 'Current programme enrolment is required for this session date.';
            }
            $placement = $this->participation->assignmentAt($group->id, $id, $on);
            if (null === $enrolment || null === $placement || $placement->enrolmentId !== $enrolment->id) {
                $issues[] = 'Current class placement is required for this session date.';
            }
            if (!$this->consented($programme->id, $programme->currentPolicyId, $id, $from, $session->endsAt)) {
                $issues[] = 'Guardian consent for the current policy must cover this session through its end.';
            }
            $result[$id] = $issues;
        }

        return $result;
    }

    private function consented(string $programmeId, ?string $policyId, string $childId, DateTimeImmutable $from, DateTimeImmutable $until): bool
    {
        if (null === $policyId) {
            return false;
        }
        foreach ($this->safeguarding->validConsents($programmeId, $policyId, $childId, $from, $until) as $consent) {
            $authority = $this->safeguarding->guardianAuthority($consent->authorityId);
            if (null !== $authority && $authority->childId === $childId && null === $authority->revokedAt && ChildrenRules::covers($authority->validFrom, $authority->validUntil, $from, $until)) {
                return true;
            }
        }

        return false;
    }
}

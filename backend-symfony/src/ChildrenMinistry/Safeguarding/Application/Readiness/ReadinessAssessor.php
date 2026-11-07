<?php

declare(strict_types=1);

namespace App\ChildrenMinistry\Safeguarding\Application\Readiness;

use App\ChildrenMinistry\Participation\Application\Contract\ParticipationReader;
use App\ChildrenMinistry\Safeguarding\Application\Contract\ChildReadiness;
use App\ChildrenMinistry\Safeguarding\Application\Contract\ReadinessDetail;
use App\ChildrenMinistry\Safeguarding\Application\Contract\ReadinessReader;
use App\ChildrenMinistry\Safeguarding\Application\Contract\ReadinessSummary;
use App\People\Application\Contract\PersonDirectory;
use App\People\Application\Contract\PersonDirectoryEntry;
use App\Shared\Application\ChurchCalendar;
use App\Shared\Domain\CalendarDate;
use DateTimeImmutable;
use Psr\Clock\ClockInterface;

/** A programme preparation snapshot, never a substitute for session admission checks. */
final readonly class ReadinessAssessor implements ChildReadiness
{
    public function __construct(
        private ReadinessReader $reader,
        private PersonDirectory $people,
        private ParticipationReader $participation,
        private ClockInterface $clock,
        private ChurchCalendar $calendar,
    ) {
    }

    public function summaries(string $programmeId, array $childIds): array
    {
        $programme = $this->participation->programme($programmeId);
        if (null === $programme) {
            return [];
        }

        return array_map(static fn (ReadinessView $view): ReadinessSummary => $view->summary(), $this->assess($programmeId, $programme->currentPolicyId, $childIds));
    }

    public function details(string $programmeId, array $childIds): array
    {
        $programme = $this->participation->programme($programmeId);
        if (null === $programme) {
            return [];
        }

        return array_map(static fn (ReadinessView $view): ReadinessDetail => $view->detail(), $this->assess($programmeId, $programme->currentPolicyId, $childIds));
    }

    /**
     * @param list<string> $childIds
     *
     * @return array<string, ReadinessView> keyed by child id
     */
    public function assess(string $programmeId, ?string $policyId, array $childIds): array
    {
        if ([] === $childIds) {
            return [];
        }
        $at = $this->clock->now();
        $on = $this->calendar->today();
        $facts = $this->reader->load($programmeId, $policyId, array_values(array_unique($childIds)), $on, $at);
        $personIds = array_values(array_unique([...$childIds, ...array_map(static fn (ReadinessCollector $collector): string => $collector->collectorId, $facts->collectors)]));
        $persons = [];
        foreach ($this->people->getAll($personIds) as $person) {
            $persons[$person->id] = $person;
        }
        $classes = $this->participation->classes($programmeId);

        $views = [];
        foreach (array_unique($childIds) as $childId) {
            $views[$childId] = $this->assessChild($childId, $policyId, $facts, $persons, $classes, $on, $at);
        }

        return $views;
    }

    /**
     * @param array<string, PersonDirectoryEntry>                            $persons
     * @param list<object{id: string, minAgeMonths: int, maxAgeMonths: int}> $classes
     */
    private function assessChild(string $childId, ?string $policyId, ReadinessFacts $facts, array $persons, array $classes, CalendarDate $on, DateTimeImmutable $at): ReadinessView
    {
        $child = $persons[$childId] ?? null;
        $birth = $child?->birthDate;
        $eligible = null !== $child && $child->available && null !== $birth && !$birth->isAfter($on) && $birth->plusYears(18)->isAfter($on);
        $enrolment = null;
        foreach ($facts->enrolments as $candidate) {
            if ($candidate->childId === $childId) {
                $enrolment = $candidate;
                break;
            }
        }
        $authorities = [];
        foreach ($facts->guardians as $guardian) {
            if ($guardian->childId === $childId) {
                $authorities[] = $guardian->id;
            }
        }
        $placed = false;
        if ($eligible && null !== $enrolment) {
            foreach ($facts->placements as $placement) {
                if ($placement->childId !== $childId || $placement->enrolmentId !== $enrolment->id) {
                    continue;
                }
                foreach ($classes as $class) {
                    if ($class->id === $placement->classId && !$birth->plusMonths($class->minAgeMonths)->isAfter($on) && $birth->plusMonths($class->maxAgeMonths)->isAfter($on)) {
                        $placed = true;
                    }
                }
            }
        }
        $consent = false;
        foreach ($facts->consents as $value) {
            $consent = $consent || ($value->childId === $childId && in_array($value->authorityId, $authorities, true));
        }
        $collector = false;
        foreach ($facts->collectors as $value) {
            $person = $persons[$value->collectorId] ?? null;
            $collector = $collector || ($value->childId === $childId && in_array($value->authorityId, $authorities, true)
                && null !== $person && $person->available && null !== $person->birthDate && !$person->birthDate->plusYears(18)->isAfter($on));
        }

        $steps = [
            new ReadinessStep('IDENTITY', $eligible),
            new ReadinessStep('ENROLMENT', null !== $enrolment),
            new ReadinessStep('CLASS', $placed),
            new ReadinessStep('POLICY', null !== $policyId),
            new ReadinessStep('GUARDIAN', [] !== $authorities),
            new ReadinessStep('CONSENT', $consent),
            new ReadinessStep('COLLECTOR', $collector),
        ];
        $completed = count(array_filter($steps, static fn (ReadinessStep $step): bool => $step->complete));

        return new ReadinessView($completed === count($steps), $completed, count($steps), $on, $at, $steps, $enrolment, $this->calendar->timeZone());
    }
}

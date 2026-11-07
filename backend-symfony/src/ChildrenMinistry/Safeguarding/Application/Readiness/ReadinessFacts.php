<?php

declare(strict_types=1);

namespace App\ChildrenMinistry\Safeguarding\Application\Readiness;

/** The current evidence about a set of children in one programme, stripped to what readiness needs. */
final readonly class ReadinessFacts
{
    /**
     * @param list<ReadinessEnrolment> $enrolments current enrolments
     * @param list<ReadinessPlacement> $placements current class placements
     * @param list<ReadinessGuardian>  $guardians  unrevoked authorities in force now
     * @param list<ReadinessConsent>   $consents   unrevoked consents to the current policy in force now
     * @param list<ReadinessCollector> $collectors unrevoked pickup grants in force now
     */
    public function __construct(
        public array $enrolments,
        public array $placements,
        public array $guardians,
        public array $consents,
        public array $collectors,
    ) {
    }
}

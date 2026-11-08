<?php

declare(strict_types=1);

namespace App\Volunteering\Application;

use App\Volunteering\Application\Contract\ServingAudit;
use App\Volunteering\Domain\AvailabilityException;
use App\Volunteering\Domain\AvailabilityRule;
use App\Volunteering\Domain\Repository\VolunteerRepository;
use App\Volunteering\Domain\ServingRules;
use App\Volunteering\Domain\VolunteerException;
use Psr\Clock\ClockInterface;

/**
 * Java `VolunteerProfiles.availability`: replaces the volunteer's availability with a new revision at the expected
 * profile version. Existing assignments stay; only readiness changes. Lock order as {@see VolunteerLocks::lock}.
 */
final readonly class VolunteerAvailability
{
    private const int LIMIT = 100;

    public function __construct(
        private VolunteerLocks $locks,
        private VolunteerRepository $volunteers,
        private ServingAudit $audit,
        private ClockInterface $clock,
    ) {
    }

    /**
     * @param list<AvailabilityRule>|null      $weekly
     * @param list<AvailabilityException>|null $exceptions
     */
    public function replace(string $actorId, string $volunteerId, int $expectedVersion, ?array $weekly, ?array $exceptions): void
    {
        if (null === $weekly || null === $exceptions || \count($weekly) > self::LIMIT || \count($exceptions) > self::LIMIT) {
            throw VolunteerException::invalid('Use at most 100 weekly rules and 100 exceptions.');
        }
        $current = $this->locks->lock($volunteerId);
        ServingRules::version($current->version, $expectedVersion);
        $now = $this->clock->now();
        $changed = $current->availability($now);
        $this->volunteers->addAvailability($volunteerId, $changed->availabilityRevision, $weekly, $exceptions);
        $this->volunteers->save($changed);
        $this->audit->record($actorId, 'volunteer.availability', $volunteerId, $now);
    }
}

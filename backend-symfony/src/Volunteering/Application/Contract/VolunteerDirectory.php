<?php

declare(strict_types=1);

namespace App\Volunteering\Application\Contract;

/** Java `VolunteerReader` beyond what readiness needs: profiles by person or account, the list, and availability with ids. */
interface VolunteerDirectory extends VolunteerReader
{
    public function byPerson(string $personId): ?VolunteerView;

    public function byAccount(string $accountId): ?VolunteerView;

    /**
     * Newest first. With person ids, only their profiles (an empty list matches nothing).
     *
     * @param list<string>|null $personIds
     *
     * @return list<VolunteerView>
     */
    public function volunteers(?array $personIds, int $limit, int $offset): array;

    /** @param list<string>|null $personIds */
    public function volunteerCount(?array $personIds): int;

    /** @return list<AvailabilityRuleRecord> by day, then start, at most 100 */
    public function weeklyRules(string $volunteerId, int $revision): array;

    /** @return list<AvailabilityExceptionRecord> by start, at most 100 */
    public function exceptionWindows(string $volunteerId, int $revision): array;
}

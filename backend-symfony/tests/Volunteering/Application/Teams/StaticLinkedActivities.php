<?php

declare(strict_types=1);

namespace App\Tests\Volunteering\Application\Teams;

use App\Volunteering\Application\Contract\LinkedActivities;
use App\Volunteering\Application\Contract\ServingActivity;

final readonly class StaticLinkedActivities implements LinkedActivities
{
    /**
     * @param list<ServingActivity> $volunteerActivities
     * @param list<ServingActivity> $teamActivities
     */
    public function __construct(private array $volunteerActivities = [], private array $teamActivities = [])
    {
    }

    public function ofVolunteers(array $volunteerIds): array
    {
        return $this->volunteerActivities;
    }

    public function ofTeam(string $teamId): array
    {
        return $this->teamActivities;
    }
}

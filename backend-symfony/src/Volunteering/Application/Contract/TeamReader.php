<?php

declare(strict_types=1);

namespace App\Volunteering\Application\Contract;

use App\Volunteering\Domain\ValidityWindow;

interface TeamReader
{
    public function team(string $id): ?TeamView;

    public function role(string $id): ?RoleView;

    /** @return list<ValidityWindow> at most 1000, newest first */
    public function memberships(string $teamId, string $volunteerId): array;

    /** @return list<ValidityWindow> at most 1000, newest first */
    public function approvals(string $roleId, string $volunteerId): array;
}

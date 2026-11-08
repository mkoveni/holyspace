<?php

declare(strict_types=1);

namespace App\Volunteering\Application\Contract;

/** The team views (Java `TeamReader` list and detail reads). Readiness keeps its narrower `TeamReader`. */
interface TeamDirectory
{
    /** @return list<TeamRecord> by name, then id */
    public function teams(int $limit, int $offset): array;

    public function teamCount(): int;

    public function team(string $id): ?TeamRecord;

    public function role(string $id): ?ServingRoleRecord;

    /** @return list<ServingRoleRecord> by name, then id, at most 100 */
    public function roles(string $teamId): array;

    /** @return list<ServingPeriod> newest first, at most 1000 */
    public function memberships(string $teamId): array;

    /** @return list<ServingPeriod> newest first, at most 1000 */
    public function approvals(string $roleId): array;
}

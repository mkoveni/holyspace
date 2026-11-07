<?php

declare(strict_types=1);

namespace App\Tests\Volunteering\Application;

use App\Volunteering\Application\Contract\AssignmentView;
use App\Volunteering\Application\Contract\CredentialTypeView;
use App\Volunteering\Application\Contract\CredentialView;
use App\Volunteering\Application\Contract\RoleView;
use App\Volunteering\Application\Contract\RosterReader;
use App\Volunteering\Application\Contract\ShiftView;
use App\Volunteering\Application\Contract\TeamReader;
use App\Volunteering\Application\Contract\TeamView;
use App\Volunteering\Application\Contract\VolunteerReader;
use App\Volunteering\Application\Contract\VolunteerView;
use App\Volunteering\Domain\AvailabilityException;
use App\Volunteering\Domain\AvailabilityRule;
use App\Volunteering\Domain\ValidityWindow;
use DateTimeImmutable;

/** One in-memory store behind the three readers; public arrays are the fixture. */
final class FakeStaffingData implements VolunteerReader, TeamReader, RosterReader
{
    /** @var array<string, VolunteerView> */
    public array $volunteers = [];

    /** @var list<CredentialTypeView> */
    public array $credentialTypes = [];

    /** @var list<CredentialView> */
    public array $credentials = [];

    /** @var list<AvailabilityRule> */
    public array $rules = [];

    /** @var list<AvailabilityException> */
    public array $exceptions = [];

    /** @var array<string, TeamView> */
    public array $teams = [];

    /** @var array<string, RoleView> */
    public array $roles = [];

    /** @var list<ValidityWindow> */
    public array $memberships = [];

    /** @var list<ValidityWindow> */
    public array $approvals = [];

    /** @var array<string, ShiftView> */
    public array $shifts = [];

    /** @var array<string, AssignmentView> */
    public array $assignments = [];

    /** @var list<AssignmentView> */
    public array $bookings = [];

    public function volunteer(string $id): ?VolunteerView
    {
        return $this->volunteers[$id] ?? null;
    }

    public function credentialTypes(): array
    {
        return $this->credentialTypes;
    }

    public function credentials(string $volunteerId): array
    {
        return $this->credentials;
    }

    public function availability(string $volunteerId, int $revision): array
    {
        return $this->rules;
    }

    public function exceptions(string $volunteerId, int $revision): array
    {
        return $this->exceptions;
    }

    public function team(string $id): ?TeamView
    {
        return $this->teams[$id] ?? null;
    }

    public function role(string $id): ?RoleView
    {
        return $this->roles[$id] ?? null;
    }

    public function memberships(string $teamId, string $volunteerId): array
    {
        return $this->memberships;
    }

    public function approvals(string $roleId, string $volunteerId): array
    {
        return $this->approvals;
    }

    public function assignment(string $id): ?AssignmentView
    {
        return $this->assignments[$id] ?? null;
    }

    public function shift(string $id): ?ShiftView
    {
        return $this->shifts[$id] ?? null;
    }

    public function sourceShifts(string $kind, string $sourceId, int $limit): array
    {
        return \array_slice(array_values(array_filter(
            $this->shifts,
            static fn (ShiftView $shift): bool => $shift->sourceKind === $kind && $shift->sourceId === $sourceId,
        )), 0, $limit);
    }

    public function assignments(string $shiftId): array
    {
        return array_values(array_filter($this->assignments, static fn (AssignmentView $a): bool => $a->shiftId === $shiftId));
    }

    public function volunteerAssignments(string $volunteerId, DateTimeImmutable $from, DateTimeImmutable $until): array
    {
        return $this->bookings;
    }
}

<?php

declare(strict_types=1);

namespace App\Tests\Volunteering\Infrastructure;

use App\Tests\Support\DatabaseTestCase;
use App\Volunteering\Application\Contract\ServingActivity;
use App\Volunteering\Domain\VolunteerException;
use App\Volunteering\Infrastructure\Persistence\DbalLinkedActivities;

/** Java `JooqRosterStore.volunteerActivities/teamActivities` on real V14 rows. */
final class DbalLinkedActivitiesTest extends DatabaseTestCase
{
    private const string VOLUNTEER = '20000000-0000-4000-8000-000000000001';
    private const string TEAM = '20000000-0000-4000-8000-000000000003';
    private const string ROLE = '20000000-0000-4000-8000-000000000004';
    private const string SESSION = '20000000-0000-4000-8000-000000000007';
    private const string OTHER_SESSION = '20000000-0000-4000-8000-000000000008';

    protected function setUp(): void
    {
        parent::setUp();
        $c = $this->connection;
        $c->executeStatement("INSERT INTO serving_volunteers (id, person_id, status, created_at, updated_at) VALUES (?, gen_random_uuid(), 'ACTIVE', now(), now())", [self::VOLUNTEER]);
        $c->executeStatement('INSERT INTO serving_teams (id, name) VALUES (?, ?)', [self::TEAM, 'Kids']);
        $c->executeStatement("INSERT INTO serving_roles (id, team_id, name, children_role) VALUES (?, ?, 'Lead', 'LEADER')", [self::ROLE, self::TEAM]);
    }

    private function shift(?string $source, ?string $assignmentStatus = null): void
    {
        $shift = $this->connection->fetchOne('SELECT gen_random_uuid()');
        $this->connection->executeStatement(
            "INSERT INTO serving_shifts (id, request_id, request_hash, team_id, role_id, source_kind, source_id, starts_at, ends_at, positions, status) VALUES (?, ?, ?, ?, ?, ?, ?, '2026-11-08 08:00:00+00', '2026-11-08 10:00:00+00', 1, 'OPEN')",
            [$shift, $shift, str_repeat('a', 64), self::TEAM, self::ROLE, null === $source ? 'STANDALONE' : 'CHILDREN_SESSION', $source],
        );
        if (null !== $assignmentStatus) {
            $this->connection->executeStatement(
                'INSERT INTO serving_assignments (id, request_id, request_hash, shift_id, volunteer_id, status, created_by, created_at) VALUES (gen_random_uuid(), gen_random_uuid(), ?, ?, ?, ?, ?, now())',
                [str_repeat('a', 64), $shift, self::VOLUNTEER, $assignmentStatus, self::VOLUNTEER],
            );
        }
    }

    public function testActivitiesComeFromLinkedShiftsOnly(): void
    {
        $this->shift(self::SESSION, 'CONFIRMED');
        $this->shift(self::SESSION, 'PROPOSED');
        $this->shift(self::OTHER_SESSION, 'CANCELLED');
        $this->shift(null, 'CONFIRMED');
        $activities = new DbalLinkedActivities($this->connection);
        $ids = static fn (array $list): array => array_map(static fn (ServingActivity $a): string => $a->kind.':'.$a->id, $list);

        self::assertSame(['CHILDREN_SESSION:'.self::SESSION], $ids($activities->ofVolunteers([self::VOLUNTEER])), 'distinct, without cancelled or standalone duties');
        self::assertSame([], $activities->ofVolunteers([]));
        $team = $ids($activities->ofTeam(self::TEAM));
        sort($team);
        self::assertSame(['CHILDREN_SESSION:'.self::SESSION, 'CHILDREN_SESSION:'.self::OTHER_SESSION], $team, 'a team change covers cancelled bookings too');
    }

    public function testMoreThanAThousandLinkedActivitiesIsAConflict(): void
    {
        $this->connection->executeStatement(
            "INSERT INTO serving_shifts (id, request_id, request_hash, team_id, role_id, source_kind, source_id, starts_at, ends_at, positions, status)
             SELECT gen_random_uuid(), gen_random_uuid(), ?, ?, ?, 'CHILDREN_SESSION', gen_random_uuid(), '2026-11-08 08:00:00+00', '2026-11-08 10:00:00+00', 1, 'OPEN' FROM generate_series(1, 1001)",
            [str_repeat('a', 64), self::TEAM, self::ROLE],
        );

        $this->expectExceptionObject(VolunteerException::conflict('Too many linked activities. Archive completed planning before changing eligibility.'));
        new DbalLinkedActivities($this->connection)->ofTeam(self::TEAM);
    }
}

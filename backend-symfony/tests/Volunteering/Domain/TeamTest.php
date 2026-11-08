<?php

declare(strict_types=1);

namespace App\Tests\Volunteering\Domain;

use App\Volunteering\Domain\RoleApproval;
use App\Volunteering\Domain\ServingRole;
use App\Volunteering\Domain\Team;
use App\Volunteering\Domain\TeamMembership;
use App\Volunteering\Domain\ValidityWindow;
use App\Volunteering\Domain\VolunteerException;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

/** Java `Team`, `ServingRole`, `TeamMembership` and `RoleApproval` record rules. */
final class TeamTest extends TestCase
{
    private const string TEAM = '00000000-0000-4000-8000-00000000007e';

    public function testTeamsStripNamesAndMoveTheirVersion(): void
    {
        $team = Team::create(self::TEAM, "  Welcome\t");

        self::assertSame('Welcome', $team->name);
        self::assertTrue($team->active);
        self::assertSame(0, $team->version);
        self::assertSame(1, $team->touch()->version);
        $changed = $team->change('Hosts', false);
        self::assertSame(['Hosts', false, 1], [$changed->name, $changed->active, $changed->version]);
        $this->assertRefused('The team is inactive.', static fn () => $changed->requireActive());
        $this->assertRefused('Enter text of at most 150 characters.', static fn () => Team::create(self::TEAM, '   '));
        $this->assertRefused('Enter text of at most 150 characters.', static fn () => Team::create(self::TEAM, str_repeat('é', 151)));
        self::assertSame(150, mb_strlen(Team::create(self::TEAM, str_repeat('é', 150))->name));
    }

    public function testRolesCheckResponsibilityAndRequirements(): void
    {
        $role = ServingRole::create('r', self::TEAM, ' Leader ', 'LEADER', ['a', 'b']);

        self::assertSame(['Leader', 'LEADER', true, ['a', 'b']], [$role->name, $role->childrenRole, $role->active, $role->requiredCredentials]);
        $this->assertRefused('Choose a children responsibility or none.', static fn () => ServingRole::create('r', self::TEAM, 'Leader', null, []));
        $this->assertRefused('Choose credential requirements from the catalogue.', static fn () => ServingRole::create('r', self::TEAM, 'Leader', 'NONE', null));
        $this->assertRefused('Choose credential requirements from the catalogue.', static fn () => ServingRole::create('r', self::TEAM, 'Leader', 'NONE', ['a', null]));
        $this->assertRefused('Choose at most 30 unique credential requirements.', static fn () => ServingRole::create('r', self::TEAM, 'Leader', 'NONE', ['a', 'a']));
        $this->assertRefused('Choose at most 30 unique credential requirements.', static fn () => ServingRole::create('r', self::TEAM, 'Leader', 'NONE', array_map(strval(...), range(1, 31))));
        $this->assertRefused('Enter text of at most 100 characters.', static fn () => ServingRole::create('r', self::TEAM, str_repeat('a', 101), 'NONE', []));

        $archived = $role->change('Lead', 'LEADER', false, ['c']);
        self::assertSame(['Lead', false, ['c']], [$archived->name, $archived->active, $archived->requiredCredentials]);
        $this->assertRefused('Create a separate role for a different children responsibility. Existing approvals must retain their scope.', static fn () => $role->change('Lead', 'ASSISTANT', true, []));
    }

    public function testMembershipsAreHalfOpenWhenComparedForOverlap(): void
    {
        $membership = TeamMembership::grant('m', self::TEAM, 'v', new DateTimeImmutable('2026-03-01T00:00:00Z'), new DateTimeImmutable('2026-04-01T00:00:00Z'));
        $window = static fn (string $from, string $until, ?string $revoked = null): ValidityWindow => new ValidityWindow(new DateTimeImmutable($from), new DateTimeImmutable($until), null === $revoked ? null : new DateTimeImmutable($revoked));

        self::assertFalse($membership->overlapsAny());
        self::assertFalse($membership->overlapsAny($window('2026-04-01T00:00:00Z', '2026-05-01T00:00:00Z'), $window('2026-02-01T00:00:00Z', '2026-03-01T00:00:00Z')));
        self::assertTrue($membership->overlapsAny($window('2026-03-31T23:59:59Z', '2026-05-01T00:00:00Z')));
        self::assertFalse($membership->overlapsAny($window('2026-03-10T00:00:00Z', '2026-03-20T00:00:00Z', '2026-03-05T00:00:00Z')), 'revoked windows never overlap');
        $this->assertRefused('The end must follow the start.', static fn () => TeamMembership::grant('m', self::TEAM, 'v', new DateTimeImmutable('2026-03-01T00:00:00Z'), new DateTimeImmutable('2026-03-01T00:00:00Z')));
    }

    public function testApprovalsAreHalfOpenWhenComparedForOverlap(): void
    {
        $approval = RoleApproval::grant('a', 'role', 'v', new DateTimeImmutable('2026-03-01T00:00:00Z'), new DateTimeImmutable('2026-04-01T00:00:00Z'));
        $window = static fn (string $from, string $until, ?string $revoked = null): ValidityWindow => new ValidityWindow(new DateTimeImmutable($from), new DateTimeImmutable($until), null === $revoked ? null : new DateTimeImmutable($revoked));

        self::assertNull($approval->revokedAt);
        self::assertFalse($approval->overlapsAny($window('2026-04-01T00:00:00Z', '2026-05-01T00:00:00Z')));
        self::assertTrue($approval->overlapsAny($window('2026-02-01T00:00:00Z', '2026-03-01T00:00:01Z')));
        self::assertFalse($approval->overlapsAny($window('2026-03-10T00:00:00Z', '2026-03-20T00:00:00Z', '2026-03-05T00:00:00Z')), 'revoked windows never overlap');
        $this->assertRefused('The end must follow the start.', static fn () => RoleApproval::grant('a', 'role', 'v', new DateTimeImmutable('2026-04-01T00:00:00Z'), new DateTimeImmutable('2026-03-01T00:00:00Z')));
    }

    private function assertRefused(string $message, callable $action): void
    {
        try {
            $action();
            self::fail('accepted: '.$message);
        } catch (VolunteerException $e) {
            self::assertSame($message, $e->getMessage());
        }
    }
}

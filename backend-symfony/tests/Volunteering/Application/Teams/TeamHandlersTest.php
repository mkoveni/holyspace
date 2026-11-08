<?php

declare(strict_types=1);

namespace App\Tests\Volunteering\Application\Teams;

use App\IdentityAccess\Domain\IdentityException;
use App\Tests\Giving\Application\RecordingPermissions;
use App\Tests\People\Application\SequentialIds;
use App\Volunteering\Application\Command\ApproveServingRole\ApproveServingRoleCommand;
use App\Volunteering\Application\Command\ApproveServingRole\ApproveServingRoleHandler;
use App\Volunteering\Application\Command\CreateServingRole\CreateServingRoleCommand;
use App\Volunteering\Application\Command\CreateServingRole\CreateServingRoleHandler;
use App\Volunteering\Application\Command\JoinServingTeam\JoinServingTeamCommand;
use App\Volunteering\Application\Command\JoinServingTeam\JoinServingTeamHandler;
use App\Volunteering\Application\Command\RevokeServingApproval\RevokeServingApprovalCommand;
use App\Volunteering\Application\Command\RevokeServingApproval\RevokeServingApprovalHandler;
use App\Volunteering\Application\Command\UpdateServingTeam\UpdateServingTeamCommand;
use App\Volunteering\Application\Command\UpdateServingTeam\UpdateServingTeamHandler;
use App\Volunteering\Application\Contract\ServingActivity;
use App\Volunteering\Application\CredentialCatalogue;
use App\Volunteering\Application\TeamLocks;
use App\Volunteering\Application\VolunteerLocks;
use App\Volunteering\Domain\ServingRole;
use App\Volunteering\Domain\Team;
use App\Volunteering\Domain\VolunteerException;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;

/**
 * Team handlers in isolation: the documented lock order (person, linked activities, volunteer row, team row; team
 * changes lock the team's activities before the team), permission first, stale versions refused, audit only on success.
 */
final class TeamHandlersTest extends TestCase
{
    private const string ACTOR = '00000000-0000-4000-8000-0000000000ac';
    private const string TEAM = '00000000-0000-4000-8000-00000000007e';
    private const string VOLUNTEER = '00000000-0000-4000-8000-0000000000b1';
    private const string PERSON = '00000000-0000-4000-8000-0000000000c1';
    private const string TYPE = '00000000-0000-4000-8000-0000000000d1';
    private const string ROLE = '00000000-0000-4000-8000-0000000000e1';
    private const string OTHER_TEAM = '00000000-0000-4000-8000-00000000007f';
    private const string OTHER_ROLE = '00000000-0000-4000-8000-0000000000e2';

    private LockJournal $journal;
    private MemoryTeams $teams;
    private MemoryServingAudit $audit;
    private RecordingPermissions $auth;
    private VolunteerLocks $volunteers;
    private TeamLocks $locks;
    private MemoryVolunteers $profiles;
    private SequentialIds $ids;

    protected function setUp(): void
    {
        $this->journal = new LockJournal();
        $this->ids = new SequentialIds();
        $this->teams = new MemoryTeams($this->journal);
        $this->teams->teams[self::TEAM] = Team::reconstitute(self::TEAM, 'Welcome', true, 3);
        $this->teams->roles[self::ROLE] = ServingRole::reconstitute(self::ROLE, self::TEAM, 'Leader', 'LEADER', false, []);
        $this->teams->roles[self::OTHER_ROLE] = ServingRole::reconstitute(self::OTHER_ROLE, self::OTHER_TEAM, 'Host', 'NONE', true, []);
        $this->audit = new MemoryServingAudit();
        $this->auth = new RecordingPermissions(self::ACTOR, ['volunteering.manage']);
        $this->profiles = new MemoryVolunteers($this->journal, [self::VOLUNTEER => self::PERSON], [self::TYPE]);
        $this->wire(new StaticLinkedActivities(
            [new ServingActivity('CHILDREN_SESSION', 'b'), new ServingActivity('CHILDREN_SESSION', 'a'), new ServingActivity('CHILDREN_SESSION', 'b')],
            [new ServingActivity('EVENT', 'z'), new ServingActivity('CHILDREN_SESSION', 'y')],
        ));
    }

    private function wire(StaticLinkedActivities $activities): void
    {
        $this->volunteers = new VolunteerLocks($this->profiles, new JournalPeople($this->journal), $activities, $this->profiles, [new JournalActivityLocks($this->journal, 'children'), new JournalActivityLocks($this->journal, 'events')]);
        $this->locks = new TeamLocks($this->teams, $this->volunteers);
    }

    private function join(string $actor = self::ACTOR, int $version = 3, string $from = '2026-03-01T08:00:00Z'): string
    {
        return new JoinServingTeamHandler($this->auth, $this->volunteers, $this->locks, $this->teams, $this->audit, $this->ids, new MockClock())
            ->handle(new JoinServingTeamCommand($actor, self::TEAM, $version, self::VOLUNTEER, new DateTimeImmutable($from), new DateTimeImmutable('2026-06-01T08:00:00Z')));
    }

    public function testJoiningLocksPersonThenActivitiesThenVolunteerThenTeam(): void
    {
        $id = $this->join();

        self::assertSame([
            'person:'.self::PERSON,
            'children:CHILDREN_SESSION:a',
            'children:CHILDREN_SESSION:b',
            'events:CHILDREN_SESSION:a',
            'events:CHILDREN_SESSION:b',
            'volunteer:'.self::VOLUNTEER,
            'team:'.self::TEAM,
        ], $this->journal->entries);
        self::assertSame(4, $this->teams->teams[self::TEAM]->version);
        self::assertSame(['team.member.added:'.$id], $this->audit->rows);
    }

    public function testLockingSeveralVolunteersFollowsPersonIdOrder(): void
    {
        $profiles = new MemoryVolunteers($this->journal, ['v1' => 'person-b', 'v2' => 'person-a', 'v3' => 'person-c']);
        $locks = new VolunteerLocks($profiles, new JournalPeople($this->journal), new StaticLinkedActivities(), $profiles, []);

        $locked = $locks->lockAll(['v1', 'v3', 'v2', 'v1'], [new ServingActivity('EVENT', 'e')]);

        self::assertSame(['v2', 'v1', 'v3'], array_map(static fn ($v): string => $v->id, $locked));
        self::assertSame(['person:person-a', 'person:person-b', 'person:person-c', 'volunteer:v2', 'volunteer:v1', 'volunteer:v3'], $this->journal->entries);
    }

    public function testTeamChangesLockTheTeamsActivitiesBeforeTheTeam(): void
    {
        new UpdateServingTeamHandler($this->auth, $this->locks, $this->teams, $this->audit, new MockClock())
            ->handle(new UpdateServingTeamCommand(self::ACTOR, self::TEAM, 3, 'Welcomers', false));

        self::assertSame(['children:CHILDREN_SESSION:y', 'children:EVENT:z', 'events:CHILDREN_SESSION:y', 'events:EVENT:z', 'team:'.self::TEAM], $this->journal->entries);
        self::assertSame(['team.changed:'.self::TEAM], $this->audit->rows);
        self::assertFalse($this->teams->teams[self::TEAM]->active);
    }

    public function testPermissionIsCheckedBeforeAnyLock(): void
    {
        try {
            $this->join(actor: '00000000-0000-4000-8000-00000000dead');
            self::fail('another actor was accepted');
        } catch (IdentityException) {
        }

        self::assertSame(['00000000-0000-4000-8000-00000000dead:volunteering.manage'], $this->auth->checks);
        self::assertSame([], $this->journal->entries);
        self::assertSame([], $this->audit->rows);
    }

    public function testStaleVersionsAndRefusedChangesWriteNothing(): void
    {
        try {
            $this->join(version: 2);
            self::fail('a stale version was accepted');
        } catch (VolunteerException $e) {
            self::assertSame('This record changed. Refresh before saving.', $e->getMessage());
        }
        $this->join();
        try {
            $this->join(version: 4, from: '2026-05-01T08:00:00Z');
            self::fail('an overlapping membership was accepted');
        } catch (VolunteerException $e) {
            self::assertSame('A team membership already covers part of this interval.', $e->getMessage());
        }

        self::assertCount(1, $this->teams->memberships);
        self::assertSame(4, $this->teams->teams[self::TEAM]->version);
        self::assertCount(1, $this->audit->rows);
    }

    public function testRoleCreationNeedsCatalogueTypesAndTouchesTheTeam(): void
    {
        $handler = new CreateServingRoleHandler($this->auth, $this->locks, $this->teams, new CredentialCatalogue($this->profiles), $this->audit, new SequentialIds(), new MockClock());
        try {
            $handler->handle(new CreateServingRoleCommand(self::ACTOR, self::TEAM, 3, 'Leader', 'LEADER', ['00000000-0000-4000-8000-00000000beef']));
            self::fail('an unknown credential type was accepted');
        } catch (VolunteerException $e) {
            self::assertSame('Credential type was not found.', $e->getMessage());
        }
        self::assertSame([], $this->audit->rows);

        $role = $handler->handle(new CreateServingRoleCommand(self::ACTOR, self::TEAM, 3, 'Leader', 'LEADER', [self::TYPE]));

        self::assertSame([self::TYPE], $this->teams->roles[$role]->requiredCredentials);
        self::assertSame(4, $this->teams->teams[self::TEAM]->version);
        self::assertSame(['team.role.created:'.$role], $this->audit->rows);
    }

    private function approve(string $actor = self::ACTOR, int $version = 3, string $role = self::ROLE, string $from = '2026-03-01T08:00:00Z'): string
    {
        return new ApproveServingRoleHandler($this->auth, $this->volunteers, $this->locks, $this->teams, $this->audit, $this->ids, new MockClock())
            ->handle(new ApproveServingRoleCommand($actor, self::TEAM, $version, self::VOLUNTEER, $role, new DateTimeImmutable($from), new DateTimeImmutable('2026-06-01T08:00:00Z')));
    }

    private function revoke(string $record, bool $roleApproval, int $version, string $actor = self::ACTOR): void
    {
        new RevokeServingApprovalHandler($this->auth, $this->volunteers, $this->locks, $this->teams, $this->audit, new MockClock('2026-07-01 10:00:00 UTC'))
            ->handle(new RevokeServingApprovalCommand($actor, self::TEAM, $version, self::VOLUNTEER, $record, $roleApproval));
    }

    public function testApprovingLocksTheVolunteerThenTheTeamAndTouchesIt(): void
    {
        $id = $this->approve();

        self::assertSame([
            'person:'.self::PERSON,
            'children:CHILDREN_SESSION:a',
            'children:CHILDREN_SESSION:b',
            'events:CHILDREN_SESSION:a',
            'events:CHILDREN_SESSION:b',
            'volunteer:'.self::VOLUNTEER,
            'team:'.self::TEAM,
        ], $this->journal->entries);
        self::assertSame(4, $this->teams->teams[self::TEAM]->version);
        self::assertSame(['team.role.approved:'.$id], $this->audit->rows);
        self::assertSame(self::ROLE, $this->teams->approvals[0]->roleId, 'an inactive role can still be approved, as in Java');
    }

    public function testApprovalsNeedARoleOfTheTeamAndCannotOverlap(): void
    {
        foreach ([self::OTHER_ROLE, '00000000-0000-4000-8000-00000000beef'] as $role) {
            try {
                $this->approve(role: $role);
                self::fail('a role of another team was accepted');
            } catch (VolunteerException $e) {
                self::assertSame('Team role was not found.', $e->getMessage());
            }
        }
        try {
            $this->approve(version: 2);
            self::fail('a stale version was accepted');
        } catch (VolunteerException $e) {
            self::assertSame('This record changed. Refresh before saving.', $e->getMessage());
        }
        $this->approve();
        try {
            $this->approve(version: 4, from: '2026-05-31T08:00:00Z');
            self::fail('an overlapping approval was accepted');
        } catch (VolunteerException $e) {
            self::assertSame('A role approval already covers part of this interval.', $e->getMessage());
        }

        self::assertCount(1, $this->teams->approvals);
        self::assertSame(4, $this->teams->teams[self::TEAM]->version);
        self::assertCount(1, $this->audit->rows);
    }

    public function testRevokingChecksPermissionFirstAndNeedsARecordOnTheTeam(): void
    {
        try {
            $this->revoke('anything', true, 3, '00000000-0000-4000-8000-00000000dead');
            self::fail('another actor was accepted');
        } catch (IdentityException) {
        }
        self::assertSame([], $this->journal->entries);

        $approval = $this->approve();
        $membership = $this->join(version: 4);
        foreach ([[$membership, true, 'Role approval'], [$approval, false, 'Membership']] as [$record, $roleApproval, $name]) {
            try {
                $this->revoke($record, $roleApproval, 5);
                self::fail('a record of the wrong kind was revoked');
            } catch (VolunteerException $e) {
                self::assertSame($name.' was not found.', $e->getMessage());
            }
        }
        self::assertSame([], $this->teams->revoked);
        self::assertSame(5, $this->teams->teams[self::TEAM]->version);
    }

    public function testRevokingKeepsTheRecordAndAuditsEachKind(): void
    {
        $approval = $this->approve();
        $membership = $this->join(version: 4);
        $this->journal->entries = [];

        $this->revoke($approval, true, 5);
        $this->revoke($membership, false, 6);
        $this->revoke($approval, true, 7);

        self::assertSame('volunteer:'.self::VOLUNTEER, $this->journal->entries[5]);
        self::assertSame('team:'.self::TEAM, $this->journal->entries[6]);
        self::assertSame([$approval, $membership], array_keys($this->teams->revoked));
        self::assertSame(8, $this->teams->teams[self::TEAM]->version);
        self::assertSame(['team.approval.revoked:'.$approval, 'team.membership.revoked:'.$membership, 'team.approval.revoked:'.$approval], \array_slice($this->audit->rows, 2));
    }
}

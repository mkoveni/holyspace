<?php

declare(strict_types=1);

namespace App\Tests\ChildrenMinistry\Sessions\Application;

use App\ChildrenMinistry\Safeguarding\Application\Contract\ChildReadiness;
use App\ChildrenMinistry\Safeguarding\Application\Contract\ReadinessDetail;
use App\ChildrenMinistry\Safeguarding\Application\Contract\ReadinessSummary;
use App\ChildrenMinistry\Safeguarding\Application\GuardianAuthorityView;
use App\ChildrenMinistry\Safeguarding\Application\PickupAuthorizationView;
use App\ChildrenMinistry\Sessions\Application\Command\IssueMyChildPickupLabel\IssueMyChildPickupLabelCommand;
use App\ChildrenMinistry\Sessions\Application\Command\IssueMyChildPickupLabel\IssueMyChildPickupLabelHandler;
use App\ChildrenMinistry\Sessions\Application\LabelIssue;
use App\ChildrenMinistry\Sessions\Application\MemberFamilyViews;
use App\ChildrenMinistry\Sessions\Application\SessionChildPreparation;
use App\ChildrenMinistry\Sessions\Domain\Attendance;
use App\ChildrenMinistry\Sessions\Infrastructure\SecurePickupTokens;
use App\ChildrenMinistry\Shared\Application\ParticipantAccess;
use App\ChildrenMinistry\Shared\Domain\ChildrenException;
use App\IdentityAccess\Domain\IdentityException;
use App\Shared\Application\ChurchCalendar;
use App\Tests\ChildrenMinistry\Participation\Application\RecordingChildrenAudit;
use App\Tests\ChildrenMinistry\Shared\CallLog;
use App\Tests\ChildrenMinistry\Shared\RecordingChildLocks;
use App\Tests\ChildrenMinistry\Shared\RecordingPeople;
use App\Tests\People\Application\SequentialIds;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;

/** The guardian-issued collection QR: lock order, the five-minute cap and the authority it may never outlive. */
final class MemberFamilyLabelTest extends TestCase
{
    private const string ACCOUNT = '77777777-7777-4777-8777-777777777777';
    private const string GUARDIAN = '20000000-0000-4000-8000-000000000002';
    private const string CHILD = '20000000-0000-4000-8000-000000000001';
    private const string AUTHORITY = '30000000-0000-4000-8000-000000000001';
    private const string VISIT = '40000000-0000-4000-8000-000000000001';

    private CallLog $log;
    private MockClock $clock;
    private SessionFixture $world;
    private FakeSafeguardingReader $safeguarding;
    private FakeMemberFamily $family;

    protected function setUp(): void
    {
        $this->log = new CallLog();
        $this->clock = new MockClock('2026-06-07 09:30:00 UTC');
        $this->world = new SessionFixture($this->log);
        $this->world->people[self::GUARDIAN] = ['Parent', '1985-01-01'];
        $this->world->people[self::CHILD] = ['Child', '2018-01-01'];
        $this->family = new FakeMemberFamily([[self::GUARDIAN, self::CHILD]]);
        $this->safeguarding = new FakeSafeguardingReader();
        $this->authority('2027-01-01T00:00:00Z');
        $this->grant('2027-01-01T00:00:00Z');
        $this->world->attendances[self::VISIT] = Attendance::arrive(self::VISIT, self::CHILD, SessionFixture::SESSION, new DateTimeImmutable('2026-06-07T09:05:00Z'), 'staff', '50000000-0000-4000-8000-000000000001');
    }

    private function authority(string $until, ?string $revoked = null): void
    {
        $this->safeguarding->authorities[self::AUTHORITY] = new GuardianAuthorityView(self::AUTHORITY, self::CHILD, self::GUARDIAN, new DateTimeImmutable('2026-01-01T00:00:00Z'), new DateTimeImmutable($until), 'private', 'v', null === $revoked ? null : new DateTimeImmutable($revoked), null, 0);
    }

    private function grant(string $until): void
    {
        $this->safeguarding->grants = [new PickupAuthorizationView('60000000-0000-4000-8000-000000000001', SessionFixture::PROGRAMME, self::CHILD, self::GUARDIAN, self::AUTHORITY, new DateTimeImmutable('2026-01-01T00:00:00Z'), new DateTimeImmutable($until), 'private', 'v', null, null, 0)];
    }

    private function handler(?string $linkedPerson = self::GUARDIAN): IssueMyChildPickupLabelHandler
    {
        $identity = new FakeMemberIdentity($this->log, self::ACCOUNT, $linkedPerson);
        $calendar = new ChurchCalendar($this->clock, 'Africa/Johannesburg');
        $readiness = new class () implements ChildReadiness {
            public function summaries(string $programmeId, array $childIds): array
            {
                return [];
            }

            public function details(string $programmeId, array $childIds): array
            {
                return ['x' => new ReadinessDetail(new ReadinessSummary(true, 0, 0), [], 'UTC')];
            }
        };
        $views = new MemberFamilyViews($this->family, new SeriesPeople($this->world), $this->world, $readiness, new SessionChildPreparation($this->world, $this->safeguarding, $this->world->operations()), $this->safeguarding, $this->world, $calendar, $this->clock);
        $access = new ParticipantAccess(new RecordingPeople($this->log, [self::GUARDIAN => true, self::CHILD => true]), new RecordingChildLocks($this->log));

        return new IssueMyChildPickupLabelHandler($identity, $identity, $views, $access, $this->world, $this->world, $this->world, $this->world, $this->safeguarding, new SecurePickupTokens(), new RecordingChildrenAudit($this->log), $this->clock, new SequentialIds());
    }

    private function issue(int $version = 0, bool $replace = true, string $child = self::CHILD): LabelIssue
    {
        return $this->handler()->handle(new IssueMyChildPickupLabelCommand(self::ACCOUNT, $child, self::VISIT, $version, $replace));
    }

    public function testLocksTheMemberLinkThenPeopleInIdOrderThenTheChildThenTheSessionThenTheVisit(): void
    {
        $this->issue();

        $locks = array_values(array_filter($this->log->entries, static fn (string $e): bool => str_starts_with($e, 'lock:') || str_starts_with($e, 'person:') || str_starts_with($e, 'guard:') || str_starts_with($e, 'forUpdate:')));
        self::assertSame(['lock:member', 'person:'.self::CHILD, 'person:'.self::GUARDIAN, 'guard:'.self::CHILD, 'forUpdate:session', 'forUpdate:attendance'], $locks);
        $write = array_search('update:attendance', $this->log->entries, true);
        self::assertGreaterThan(array_search('forUpdate:attendance', $this->log->entries, true), $write);
        self::assertSame(['update:attendance', 'insert:event:LABEL_ISSUED', 'audit:pickup.member_label.issued'], array_values(array_filter($this->log->entries, static fn (string $e): bool => str_starts_with($e, 'update:') || str_starts_with($e, 'insert:') || str_starts_with($e, 'audit:'))));
    }

    public function testTheLabelLastsFiveMinutesAndStoresOnlyItsHash(): void
    {
        $label = $this->issue();

        self::assertEquals($this->clock->now()->modify('+300 seconds'), $label->expiresAt);
        $stored = $this->world->attendances[self::VISIT];
        self::assertSame(hash('sha256', $label->pickupCode), $stored->labelHash);
        self::assertSame([1, 1], [$label->labelVersion, $label->attendanceVersion]);
        self::assertSame('Member requested a replacement collection QR.', $this->world->events[0]->reason);
        self::assertSame(self::ACCOUNT, $this->world->events[0]->actorId);
    }

    public function testTheLabelNeverOutlivesTheAuthorityOrTheGrant(): void
    {
        $this->authority('2026-06-07T09:32:00Z');
        self::assertEquals(new DateTimeImmutable('2026-06-07T09:32:00Z'), $this->issue()->expiresAt);

        $this->authority('2027-01-01T00:00:00Z');
        $this->grant('2026-06-07T09:31:00Z');
        self::assertEquals(new DateTimeImmutable('2026-06-07T09:31:00Z'), $this->issue(1)->expiresAt);
    }

    public function testRefusesWithoutOwnCurrentGrantOrAuthority(): void
    {
        $this->safeguarding->grants = [];
        $this->expectException(ChildrenException::class);
        $this->expectExceptionMessage('Your own current pickup authorization is required.');
        try {
            $this->issue();
        } finally {
            self::assertNotContains('update:attendance', $this->log->entries);
        }
    }

    public function testRevokedAuthorityMakesTheChildNotFoundAndWritesNothing(): void
    {
        $this->family->pairs = [];

        try {
            $this->issue();
            self::fail('Expected not found.');
        } catch (ChildrenException $missing) {
            self::assertSame('Authorized child was not found.', $missing->getMessage());
        }
        self::assertSame([], $this->log->entries, 'nothing is locked or written before authority is proven');
    }

    public function testAGuardianAuthorityRevokedAfterTheGrantWasIssuedBlocksTheLabel(): void
    {
        $this->authority('2027-01-01T00:00:00Z', '2026-06-07T09:00:00Z');

        $this->expectExceptionMessage('Your own current pickup authorization is required.');
        $this->issue();
    }

    public function testReplacementMustBeConfirmedBeforeAnyLock(): void
    {
        try {
            $this->issue(0, false);
            self::fail('Expected a rejection.');
        } catch (ChildrenException $invalid) {
            self::assertSame('Confirm that any previous collection label will be replaced.', $invalid->getMessage());
        }
        self::assertNotContains('lock:member', $this->log->entries);
    }

    public function testStaleVersionsUnknownAndForeignVisitsAreRefused(): void
    {
        try {
            $this->issue(5);
            self::fail('Expected a conflict.');
        } catch (ChildrenException $conflict) {
            self::assertStringContainsString('changed', $conflict->getMessage());
        }
        $this->world->attendances[self::VISIT] = Attendance::arrive(self::VISIT, '20000000-0000-4000-8000-0000000000ff', SessionFixture::SESSION, new DateTimeImmutable('2026-06-07T09:05:00Z'), 'staff', '50000000-0000-4000-8000-000000000002');
        $this->expectExceptionMessage('Attendance was not found.');
        $this->issue();
    }

    public function testAnUnverifiedAccountIsForbidden(): void
    {
        $this->expectException(IdentityException::class);
        $this->handler(null)->handle(new IssueMyChildPickupLabelCommand(self::ACCOUNT, self::CHILD, self::VISIT, 0, true));
    }
}

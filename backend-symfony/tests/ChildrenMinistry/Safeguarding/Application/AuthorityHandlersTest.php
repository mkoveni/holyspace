<?php

declare(strict_types=1);

namespace App\Tests\ChildrenMinistry\Safeguarding\Application;

use App\ChildrenMinistry\Safeguarding\Application\Command\AuthorizePickup\AuthorizePickupCommand;
use App\ChildrenMinistry\Safeguarding\Application\Command\AuthorizePickup\AuthorizePickupHandler;
use App\ChildrenMinistry\Safeguarding\Application\Command\RevokeGuardian\RevokeGuardianCommand;
use App\ChildrenMinistry\Safeguarding\Application\Command\RevokeGuardian\RevokeGuardianHandler;
use App\ChildrenMinistry\Safeguarding\Application\Command\RevokePickupAuthorization\RevokePickupAuthorizationCommand;
use App\ChildrenMinistry\Safeguarding\Application\Command\RevokePickupAuthorization\RevokePickupAuthorizationHandler;
use App\ChildrenMinistry\Safeguarding\Application\Command\VerifyGuardian\VerifyGuardianCommand;
use App\ChildrenMinistry\Safeguarding\Application\Command\VerifyGuardian\VerifyGuardianHandler;
use App\ChildrenMinistry\Safeguarding\Domain\GuardianAuthority;
use App\ChildrenMinistry\Safeguarding\Domain\PickupAuthorization;
use App\ChildrenMinistry\Shared\Application\ParticipantAccess;
use App\ChildrenMinistry\Shared\Domain\ChildrenException;
use App\IdentityAccess\Domain\IdentityException;
use App\Shared\Application\ChurchCalendar;
use App\Tests\ChildrenMinistry\Participation\Application\RecordingChildrenAudit;
use App\Tests\ChildrenMinistry\Participation\Application\RecordingParticipationReader;
use App\Tests\ChildrenMinistry\Shared\CallLog;
use App\Tests\ChildrenMinistry\Shared\RecordingChildLocks;
use App\Tests\ChildrenMinistry\Shared\RecordingPeople;
use App\Tests\People\Application\SequentialIds;
use App\Tests\Support\AllowedPermissions;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;

/** The order of operations is the safety property: permission, then people and guard rows, then the record. */
final class AuthorityHandlersTest extends TestCase
{
    private const string ACTOR = '99999999-9999-4999-8999-999999999999';
    private const string CHILD = '11111111-1111-4111-8111-111111111111';
    private const string ADULT = '22222222-2222-4222-8222-222222222222';
    private const string AUTHORITY = '33333333-3333-4333-8333-333333333333';
    private const string PROGRAMME = '44444444-4444-4444-8444-444444444444';
    private const string GRANT = '55555555-5555-4555-8555-555555555555';

    private CallLog $log;
    private RecordingSafeguardingRepository $repository;
    private MockClock $clock;

    protected function setUp(): void
    {
        $this->log = new CallLog();
        $this->repository = new RecordingSafeguardingRepository($this->log);
        $this->clock = new MockClock('2026-06-01 10:00:00 UTC');
        $this->repository->authorities[self::AUTHORITY] = $this->authority();
        $this->repository->grants[self::GRANT] = PickupAuthorization::grant(self::GRANT, self::PROGRAMME, self::CHILD, self::ADULT, $this->authority(), $this->at('2026-06-01T09:00:00Z'), $this->at('2026-06-01T11:00:00Z'), 'ID', self::ACTOR);
    }

    public function testVerifyingLocksPeopleInIdOrderThenTheGuardRowBeforeWriting(): void
    {
        $this->verifyHandler()->handle($this->verifyCommand());

        self::assertSame(['person:'.self::CHILD, 'person:'.self::ADULT, 'guard:'.self::CHILD, 'insert:authority', 'audit:guardian.authority.verified'], $this->log->entries);
    }

    public function testWithoutPermissionNothingIsLockedOrWritten(): void
    {
        foreach ([
            fn () => $this->verifyHandler(['children.manage'])->handle($this->verifyCommand()),
            fn () => $this->revokeHandler(['children.read'])->handle(new RevokeGuardianCommand(self::ACTOR, self::AUTHORITY, 0, 'Why')),
            fn () => $this->authorizeHandler(['children.manage'])->handle($this->authorizeCommand()),
            fn () => $this->revokeGrantHandler(['children.read'])->handle(new RevokePickupAuthorizationCommand(self::ACTOR, self::GRANT, 0, 'Why')),
        ] as $action) {
            try {
                $action();
                self::fail('Expected a refusal.');
            } catch (IdentityException) {
            }
        }

        self::assertSame([], $this->log->entries);
    }

    public function testAGuardianMustBeAnAdultAndTheChildUnderEighteen(): void
    {
        $this->expectChildrenFailure('A recorded birth date confirming adulthood is required.', fn () => $this->verifyHandler(guardianBirth: '2015-01-01')->handle($this->verifyCommand()));
        self::assertSame(['person:'.self::CHILD, 'person:'.self::ADULT, 'guard:'.self::CHILD], $this->log->entries);

        $this->log->entries = [];
        $this->expectChildrenFailure('A recorded birth date identifying a child under 18 is required.', fn () => $this->verifyHandler(childBirth: '1990-01-01')->handle($this->verifyCommand()));
        self::assertNotContains('insert:authority', $this->log->entries);
    }

    public function testRevokingReadsThenLocksTheChildThenRereadsForUpdate(): void
    {
        $this->revokeHandler()->handle(new RevokeGuardianCommand(self::ACTOR, self::AUTHORITY, 0, 'Order'));

        self::assertSame(['read:authority', 'person:'.self::CHILD, 'guard:'.self::CHILD, 'forUpdate:authority', 'update:authority', 'audit:guardianauthority.revoked'], $this->log->entries);
        self::assertNotNull($this->repository->authorities[self::AUTHORITY]->revokedAt);
    }

    public function testAStaleRevocationWritesAndAuditsNothing(): void
    {
        $this->expectChildrenFailure('This record changed. Reload before trying again.', fn () => $this->revokeHandler()->handle(new RevokeGuardianCommand(self::ACTOR, self::AUTHORITY, 7, 'Order')));

        self::assertSame(['read:authority', 'person:'.self::CHILD, 'guard:'.self::CHILD, 'forUpdate:authority'], $this->log->entries);
    }

    public function testRevokingAnUnknownAuthorityLocksNothing(): void
    {
        $this->expectChildrenFailure('GuardianAuthority was not found.', fn () => $this->revokeHandler()->handle(new RevokeGuardianCommand(self::ACTOR, self::GRANT, 0, 'Order')));

        self::assertSame(['read:authority'], $this->log->entries);
    }

    public function testAuthorizingLocksChildAndCollectorThenChecksProgrammeAndAuthority(): void
    {
        $this->authorizeHandler()->handle($this->authorizeCommand());

        self::assertSame(['person:'.self::CHILD, 'person:'.self::ADULT, 'guard:'.self::CHILD, 'read:programme', 'find:authority', 'insert:grant', 'audit:pickup.authorized'], $this->log->entries);
    }

    public function testAuthorizingRefusesAnAuthorityThatDoesNotCoverThePeriodBeforeWriting(): void
    {
        $command = new AuthorizePickupCommand(self::ACTOR, self::PROGRAMME, self::CHILD, self::ADULT, self::AUTHORITY, $this->at('2026-06-01T09:00:00Z'), $this->at('2026-06-01T13:00:00Z'), 'ID');

        $this->expectChildrenFailure('Pickup permission requires verified guardian authority covering its period.', fn () => $this->authorizeHandler()->handle($command));

        self::assertNotContains('insert:grant', $this->log->entries);
    }

    public function testAuthorizingNeedsAnAdultCollectorAnExistingProgrammeAndAuthority(): void
    {
        $this->expectChildrenFailure('A recorded birth date confirming adulthood is required.', fn () => $this->authorizeHandler(collectorBirth: '2015-01-01')->handle($this->authorizeCommand()));
        $this->log->entries = [];
        $this->expectChildrenFailure('Programme was not found.', fn () => $this->authorizeHandler(programmes: [])->handle($this->authorizeCommand()));
        $this->log->entries = [];
        $this->repository->authorities = [];
        $this->expectChildrenFailure('GuardianAuthority was not found.', fn () => $this->authorizeHandler()->handle($this->authorizeCommand()));
        self::assertNotContains('insert:grant', $this->log->entries);
    }

    public function testRevokingAGrantFollowsTheSameLockOrder(): void
    {
        $this->revokeGrantHandler()->handle(new RevokePickupAuthorizationCommand(self::ACTOR, self::GRANT, 0, 'Moved'));

        self::assertSame(['read:grant', 'person:'.self::CHILD, 'guard:'.self::CHILD, 'forUpdate:grant', 'update:grant', 'audit:pickupauthorization.revoked'], $this->log->entries);
    }

    /** @param list<string> $granted */
    private function verifyHandler(array $granted = ['children.safeguarding'], string $childBirth = '2016-03-01', string $guardianBirth = '1985-03-01'): VerifyGuardianHandler
    {
        return new VerifyGuardianHandler(
            new AllowedPermissions($granted),
            new RecordingChildrenAudit($this->log),
            $this->clock,
            new SequentialIds(),
            $this->repository,
            $this->access($childBirth, $guardianBirth),
            new ChurchCalendar($this->clock, 'Africa/Johannesburg'),
        );
    }

    /** @param list<string> $granted */
    private function revokeHandler(array $granted = ['children.safeguarding']): RevokeGuardianHandler
    {
        return new RevokeGuardianHandler(new AllowedPermissions($granted), new RecordingChildrenAudit($this->log), $this->clock, $this->repository, $this->repository, $this->access());
    }

    /** @param list<string> $granted */
    private function revokeGrantHandler(array $granted = ['children.safeguarding']): RevokePickupAuthorizationHandler
    {
        return new RevokePickupAuthorizationHandler(new AllowedPermissions($granted), new RecordingChildrenAudit($this->log), $this->clock, $this->repository, $this->repository, $this->access());
    }

    /**
     * @param list<string> $granted
     * @param list<string> $programmes
     */
    private function authorizeHandler(array $granted = ['children.safeguarding'], string $collectorBirth = '1985-03-01', array $programmes = [self::PROGRAMME]): AuthorizePickupHandler
    {
        return new AuthorizePickupHandler(
            new AllowedPermissions($granted),
            new RecordingChildrenAudit($this->log),
            $this->clock,
            new SequentialIds(),
            $this->repository,
            new RecordingParticipationReader($this->log, $programmes),
            $this->access('2016-03-01', $collectorBirth),
            new ChurchCalendar($this->clock, 'Africa/Johannesburg'),
        );
    }

    private function access(string $childBirth = '2016-03-01', string $adultBirth = '1985-03-01'): ParticipantAccess
    {
        return new ParticipantAccess(
            new RecordingPeople($this->log, [self::CHILD => true, self::ADULT => true], [self::CHILD => $childBirth, self::ADULT => $adultBirth]),
            new RecordingChildLocks($this->log),
        );
    }

    private function verifyCommand(): VerifyGuardianCommand
    {
        return new VerifyGuardianCommand(self::ACTOR, self::CHILD, self::ADULT, $this->at('2026-06-01T08:00:00Z'), $this->at('2026-06-01T12:00:00Z'), 'Birth certificate');
    }

    private function authorizeCommand(): AuthorizePickupCommand
    {
        return new AuthorizePickupCommand(self::ACTOR, self::PROGRAMME, self::CHILD, self::ADULT, self::AUTHORITY, $this->at('2026-06-01T09:00:00Z'), $this->at('2026-06-01T11:00:00Z'), 'ID');
    }

    private function authority(): GuardianAuthority
    {
        return GuardianAuthority::verify(self::AUTHORITY, self::CHILD, self::ADULT, $this->at('2026-06-01T08:00:00Z'), $this->at('2026-06-01T12:00:00Z'), 'Birth certificate', self::ACTOR);
    }

    private function at(string $time): DateTimeImmutable
    {
        return new DateTimeImmutable($time);
    }

    private function expectChildrenFailure(string $message, callable $action): void
    {
        try {
            $action();
            self::fail('Expected a rejection.');
        } catch (ChildrenException $error) {
            self::assertSame($message, $error->getMessage());
        }
    }
}

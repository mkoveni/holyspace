<?php

declare(strict_types=1);

namespace App\Tests\ChildrenMinistry\Safeguarding\Application;

use App\ChildrenMinistry\Participation\Domain\Programme;
use App\ChildrenMinistry\Safeguarding\Application\Command\PublishConsentPolicy\PublishConsentPolicyCommand;
use App\ChildrenMinistry\Safeguarding\Application\Command\PublishConsentPolicy\PublishConsentPolicyHandler;
use App\ChildrenMinistry\Safeguarding\Application\Command\RecordConsent\RecordConsentCommand;
use App\ChildrenMinistry\Safeguarding\Application\Command\RecordConsent\RecordConsentHandler;
use App\ChildrenMinistry\Safeguarding\Application\Command\RevokeConsent\RevokeConsentCommand;
use App\ChildrenMinistry\Safeguarding\Application\Command\RevokeConsent\RevokeConsentHandler;
use App\ChildrenMinistry\Safeguarding\Domain\Consent;
use App\ChildrenMinistry\Safeguarding\Domain\ConsentPolicy;
use App\ChildrenMinistry\Safeguarding\Domain\GuardianAuthority;
use App\ChildrenMinistry\Shared\Application\ParticipantAccess;
use App\ChildrenMinistry\Shared\Domain\ChildrenException;
use App\IdentityAccess\Domain\IdentityException;
use App\Tests\ChildrenMinistry\Participation\Application\RecordingChildrenAudit;
use App\Tests\ChildrenMinistry\Participation\Application\RecordingParticipationRepository;
use App\Tests\ChildrenMinistry\Shared\CallLog;
use App\Tests\ChildrenMinistry\Shared\RecordingChildLocks;
use App\Tests\ChildrenMinistry\Shared\RecordingPeople;
use App\Tests\People\Application\SequentialIds;
use App\Tests\Support\AllowedPermissions;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;

final class ConsentHandlersTest extends TestCase
{
    private const string ACTOR = '99999999-9999-4999-8999-999999999999';
    private const string CHILD = '11111111-1111-4111-8111-111111111111';
    private const string ADULT = '22222222-2222-4222-8222-222222222222';
    private const string AUTHORITY = '33333333-3333-4333-8333-333333333333';
    private const string PROGRAMME = '44444444-4444-4444-8444-444444444444';
    private const string POLICY = '55555555-5555-4555-8555-555555555555';
    private const string CONSENT = '66666666-6666-4666-8666-666666666666';

    private CallLog $log;
    private RecordingSafeguardingRepository $repository;
    private MockClock $clock;

    protected function setUp(): void
    {
        $this->log = new CallLog();
        $this->repository = new RecordingSafeguardingRepository($this->log);
        $this->clock = new MockClock('2026-06-01 10:00:00 UTC');
        $authority = GuardianAuthority::verify(self::AUTHORITY, self::CHILD, self::ADULT, $this->at('2026-06-01T00:00:00Z'), $this->at('2026-12-01T00:00:00Z'), 'Certificate', self::ACTOR);
        $this->repository->authorities[self::AUTHORITY] = $authority;
        $this->repository->policies[self::POLICY] = ConsentPolicy::publish(self::POLICY, self::PROGRAMME, 'v1', 'docs/v1', str_repeat('a', 64), $this->at('2026-05-01T00:00:00Z'));
        $this->repository->consents[self::CONSENT] = Consent::record(self::CONSENT, self::PROGRAMME, $this->repository->policies[self::POLICY], self::CHILD, $authority, $this->at('2026-06-01T00:00:00Z'), $this->at('2026-07-01T00:00:00Z'), $this->at('2026-06-01T09:00:00Z'), 'Signed', self::ADULT, $this->at('2026-06-01T09:30:00Z'));
    }

    public function testRecordingLocksTheChildBeforeReadingAuthorityAndPolicy(): void
    {
        $this->recordHandler()->handle($this->recordCommand());

        self::assertSame(['person:'.self::CHILD, 'guard:'.self::CHILD, 'find:authority', 'find:policy', 'insert:consent', 'audit:consent.recorded'], $this->log->entries);
    }

    public function testRecordingNeedsThePermissionAndTouchesNothingWithout(): void
    {
        $this->expectException(IdentityException::class);
        try {
            $this->recordHandler(['children.manage'])->handle($this->recordCommand());
        } finally {
            self::assertSame([], $this->log->entries);
        }
    }

    public function testEveryFlawedAcceptanceIsRefusedWithTheSameMessageBeforeWriting(): void
    {
        $cases = [
            'acceptance in the future' => ['acceptedAt' => '2026-06-01T10:00:01Z'],
            'acceptance before the policy existed' => ['acceptedAt' => '2026-04-30T00:00:00Z'],
            'acceptance before the authority began' => ['acceptedAt' => '2026-05-31T23:00:00Z'],
            'consent outlasts the authority' => ['validUntil' => '2027-01-01T00:00:00Z'],
            'consent starts before the authority' => ['validFrom' => '2026-05-01T00:00:00Z'],
        ];
        foreach ($cases as $name => $change) {
            $this->log->entries = [];
            try {
                $this->recordHandler()->handle($this->recordCommand($change));
                self::fail($name);
            } catch (ChildrenException $error) {
                self::assertSame('Consent requires verified authority, the correct policy, and evidenced acceptance within its authority period.', $error->getMessage(), $name);
            }
            self::assertNotContains('insert:consent', $this->log->entries, $name);
        }
    }

    public function testAPolicyOfAnotherProgrammeOrAnotherChildsAuthorityIsRefused(): void
    {
        $this->repository->policies[self::POLICY] = ConsentPolicy::publish(self::POLICY, '77777777-7777-4777-8777-777777777777', 'v1', 'docs/v1', str_repeat('a', 64), $this->at('2026-05-01T00:00:00Z'));
        $this->expectExceptionMessage('Consent requires verified authority');

        $this->recordHandler()->handle($this->recordCommand());
    }

    public function testUnknownAuthorityOrPolicyIsNotFound(): void
    {
        $this->repository->policies = [];
        $this->expectExceptionMessage('ConsentPolicy was not found.');

        $this->recordHandler()->handle($this->recordCommand());
    }

    public function testRevokingFollowsTheReadLockReadForUpdateOrder(): void
    {
        $handler = new RevokeConsentHandler(new AllowedPermissions(['children.safeguarding']), new RecordingChildrenAudit($this->log), $this->clock, $this->repository, $this->repository, $this->access());

        $handler->handle(new RevokeConsentCommand(self::ACTOR, self::CONSENT, 0, 'Withdrawn'));

        self::assertSame(['read:consent', 'person:'.self::CHILD, 'guard:'.self::CHILD, 'forUpdate:consent', 'update:consent', 'audit:consent.revoked'], $this->log->entries);
    }

    public function testPublishingLocksTheProgrammeChecksItsVersionThenWritesPolicyAndProgramme(): void
    {
        $programmes = $this->programmes();

        $this->publishHandler($programmes)->handle(new PublishConsentPolicyCommand(self::ACTOR, self::PROGRAMME, 2, 'v2', 'docs/v2', str_repeat('b', 64)));

        self::assertSame(['forUpdate:programme', 'insert:policy', 'update:programme', 'audit:consent.policy.published'], $this->log->entries);
    }

    public function testPublishingRefusesAStaleProgrammeVersionAndABadHashBeforeWriting(): void
    {
        foreach ([[1, str_repeat('b', 64), 'This record changed. Reload before trying again.'], [2, 'ABC', 'Supply the policy document SHA-256 hash.']] as [$version, $hash, $message]) {
            $this->log->entries = [];
            try {
                $this->publishHandler($this->programmes())->handle(new PublishConsentPolicyCommand(self::ACTOR, self::PROGRAMME, $version, 'v2', 'docs/v2', $hash));
                self::fail('Expected a rejection.');
            } catch (ChildrenException $error) {
                self::assertSame($message, $error->getMessage());
            }
            self::assertSame(['forUpdate:programme'], $this->log->entries);
        }
    }

    private function programmes(): RecordingParticipationRepository
    {
        $repository = new RecordingParticipationRepository($this->log);
        $repository->programmeRow = Programme::reconstitute(self::PROGRAMME, 'Sunday', null, 1, 2, 2, $this->at('2026-01-01T00:00:00Z'));

        return $repository;
    }

    private function publishHandler(RecordingParticipationRepository $programmes): PublishConsentPolicyHandler
    {
        return new PublishConsentPolicyHandler(new AllowedPermissions(['children.manage']), new RecordingChildrenAudit($this->log), $this->clock, new SequentialIds(), $programmes, $this->repository);
    }

    /** @param list<string> $granted */
    private function recordHandler(array $granted = ['children.safeguarding']): RecordConsentHandler
    {
        return new RecordConsentHandler(new AllowedPermissions($granted), new RecordingChildrenAudit($this->log), $this->clock, new SequentialIds(), $this->repository, $this->access());
    }

    /** @param array<string, string> $change */
    private function recordCommand(array $change = []): RecordConsentCommand
    {
        $v = $change + ['validFrom' => '2026-06-01T00:00:00Z', 'validUntil' => '2026-07-01T00:00:00Z', 'acceptedAt' => '2026-06-01T09:00:00Z'];

        return new RecordConsentCommand(self::ACTOR, self::CHILD, self::PROGRAMME, self::POLICY, self::AUTHORITY, $this->at($v['validFrom']), $this->at($v['validUntil']), $this->at($v['acceptedAt']), 'Signed form');
    }

    private function access(): ParticipantAccess
    {
        return new ParticipantAccess(new RecordingPeople($this->log, [self::CHILD => true], [self::CHILD => '2016-03-01']), new RecordingChildLocks($this->log));
    }

    private function at(string $time): DateTimeImmutable
    {
        return new DateTimeImmutable($time);
    }
}

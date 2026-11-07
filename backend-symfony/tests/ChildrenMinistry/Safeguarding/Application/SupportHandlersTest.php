<?php

declare(strict_types=1);

namespace App\Tests\ChildrenMinistry\Safeguarding\Application;

use App\ChildrenMinistry\Safeguarding\Application\Command\AccessChildSupport\AccessChildSupportCommand;
use App\ChildrenMinistry\Safeguarding\Application\Command\AccessChildSupport\AccessChildSupportHandler;
use App\ChildrenMinistry\Safeguarding\Application\Command\PurgeExpiredSupport\PurgeExpiredSupportCommand;
use App\ChildrenMinistry\Safeguarding\Application\Command\PurgeExpiredSupport\PurgeExpiredSupportHandler;
use App\ChildrenMinistry\Safeguarding\Application\Command\SaveChildSupport\SaveChildSupportCommand;
use App\ChildrenMinistry\Safeguarding\Application\Command\SaveChildSupport\SaveChildSupportHandler;
use App\ChildrenMinistry\Safeguarding\Domain\SupportRecord;
use App\ChildrenMinistry\Shared\Application\ParticipantAccess;
use App\ChildrenMinistry\Shared\Domain\ChildrenException;
use App\IdentityAccess\Domain\IdentityException;
use App\Shared\Application\ChurchCalendar;
use App\Shared\Domain\CalendarDate;
use App\Tests\ChildrenMinistry\Participation\Application\RecordingChildrenAudit;
use App\Tests\ChildrenMinistry\Shared\CallLog;
use App\Tests\ChildrenMinistry\Shared\RecordingChildLocks;
use App\Tests\ChildrenMinistry\Shared\RecordingPeople;
use App\Tests\Support\AllowedPermissions;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;

final class SupportHandlersTest extends TestCase
{
    private const string ACTOR = '99999999-9999-4999-8999-999999999999';
    private const string CHILD = '11111111-1111-4111-8111-111111111111';
    private const string OTHER = '22222222-2222-4222-8222-222222222222';
    private const string SESSION = '33333333-3333-4333-8333-333333333333';

    private CallLog $log;
    private RecordingSupportStore $store;
    private MockClock $clock;

    protected function setUp(): void
    {
        $this->log = new CallLog();
        $this->store = new RecordingSupportStore($this->log);
        $this->clock = new MockClock('2026-06-01 10:00:00 UTC');
    }

    public function testSavingNeedsBothPermissionsAndTouchesNothingWithoutThem(): void
    {
        foreach ([['children.manage'], ['children.support'], ['children.read']] as $granted) {
            try {
                $this->saveHandler($granted)->handle($this->saveCommand());
                self::fail('Expected a refusal.');
            } catch (IdentityException) {
            }
        }

        self::assertSame([], $this->log->entries);
    }

    public function testCreatingLocksTheChildReadsForUpdateThenInsertsAndAudits(): void
    {
        $this->saveHandler()->handle($this->saveCommand());

        self::assertSame(['person:'.self::CHILD, 'guard:'.self::CHILD, 'forUpdate:support', 'insert:support', 'audit:support.updated'], $this->log->entries);
    }

    public function testUpdatingChecksTheVersionBeforeWriting(): void
    {
        $this->store->records[self::CHILD] = $this->record();

        $this->saveHandler()->handle($this->saveCommand(version: 0));
        self::assertSame(1, $this->store->records[self::CHILD]->version);

        $this->log->entries = [];
        $this->expectChildrenFailure('This record changed. Reload before trying again.', fn () => $this->saveHandler()->handle($this->saveCommand(version: 0)));
        self::assertSame(['person:'.self::CHILD, 'guard:'.self::CHILD, 'forUpdate:support'], $this->log->entries);
    }

    public function testCreatingExpectsVersionZeroAndPastDatesAreRefused(): void
    {
        $this->expectChildrenFailure('This record changed. Reload before trying again.', fn () => $this->saveHandler()->handle($this->saveCommand(version: 3)));
        $this->expectChildrenFailure('Review and retention dates must not be in the past.', fn () => $this->saveHandler()->handle($this->saveCommand(review: '2026-05-31')));
        $this->expectChildrenFailure('Review and retention dates must not be in the past.', fn () => $this->saveHandler()->handle($this->saveCommand(retain: '2026-05-31', review: '2026-05-30')));
        self::assertNotContains('insert:support', $this->log->entries);
    }

    public function testOnlyChildrenCanHaveSupportRecords(): void
    {
        $this->expectChildrenFailure('A recorded birth date identifying a child under 18 is required.', fn () => $this->saveHandler(birth: '1980-01-01')->handle($this->saveCommand()));
        self::assertNotContains('insert:support', $this->log->entries);
    }

    public function testAccessWithoutASessionNeedsManageAndAudits(): void
    {
        $this->store->records[self::CHILD] = $this->record();

        $record = $this->accessHandler()->handle(new AccessChildSupportCommand(self::ACTOR, self::CHILD, null));

        self::assertSame('Peanuts', $record->allergies);
        self::assertSame(['read:support', 'audit:support.accessed'], $this->log->entries);
        $this->log->entries = [];
        try {
            $this->accessHandler(['children.support'])->handle(new AccessChildSupportCommand(self::ACTOR, self::CHILD, null));
            self::fail('Expected a refusal.');
        } catch (IdentityException) {
        }
        self::assertSame([], $this->log->entries, 'no read and no audit without permission');
    }

    public function testSessionScopedAccessIsDecidedByTheScopeBeforeAnyRead(): void
    {
        $this->store->records[self::CHILD] = $this->record();

        $this->accessHandler(['children.support'])->handle(new AccessChildSupportCommand(self::ACTOR, self::CHILD, self::SESSION));

        self::assertSame(['scope:'.self::SESSION, 'read:support', 'audit:support.accessed'], $this->log->entries);
    }

    public function testMissingOrExpiredRecordsAreNotFoundAndNotAudited(): void
    {
        $this->expectChildrenFailure('Current support record was not found.', fn () => $this->accessHandler()->handle(new AccessChildSupportCommand(self::ACTOR, self::CHILD, null)));
        $this->store->records[self::CHILD] = SupportRecord::reconstitute(self::CHILD, 'Peanuts', '', '', CalendarDate::parse('2026-01-01'), CalendarDate::parse('2026-05-31'), 0, new DateTimeImmutable('2026-01-01T00:00:00Z'));
        $this->expectChildrenFailure('Current support record was not found.', fn () => $this->accessHandler()->handle(new AccessChildSupportCommand(self::ACTOR, self::CHILD, null)));
        self::assertNotContains('audit:support.accessed', $this->log->entries);
    }

    public function testPurgeLocksEachChildAndRechecksUnderTheLock(): void
    {
        $this->store->records[self::CHILD] = SupportRecord::reconstitute(self::CHILD, 'x', '', '', CalendarDate::parse('2026-01-01'), CalendarDate::parse('2026-05-31'), 0, new DateTimeImmutable('2026-01-01T00:00:00Z'));
        $this->store->records[self::OTHER] = $this->record();
        $this->store->listedExpired = [self::CHILD, self::OTHER];

        $count = $this->purgeHandler()->handle(new PurgeExpiredSupportCommand(self::ACTOR, 10));

        self::assertSame(1, $count);
        self::assertSame(
            ['list:expired', 'person:'.self::CHILD, 'guard:'.self::CHILD, 'forUpdate:support', 'purge:'.self::CHILD, 'audit:support.purged', 'person:'.self::OTHER, 'guard:'.self::OTHER, 'forUpdate:support'],
            $this->log->entries,
        );
        self::assertArrayHasKey(self::OTHER, $this->store->records, 'a renewed record is kept');
    }

    public function testPurgeNeedsBothPermissionsAndASaneLimit(): void
    {
        foreach ([['children.support'], ['children.supervise']] as $granted) {
            try {
                $this->purgeHandler($granted)->handle(new PurgeExpiredSupportCommand(self::ACTOR, 10));
                self::fail('Expected a refusal.');
            } catch (IdentityException) {
            }
        }
        self::assertSame([], $this->log->entries);
        foreach ([0, 101] as $limit) {
            $this->expectChildrenFailure('Invalid paging parameters.', fn () => $this->purgeHandler()->handle(new PurgeExpiredSupportCommand(self::ACTOR, $limit)));
        }
    }

    /** @param list<string> $granted */
    private function saveHandler(array $granted = ['children.manage', 'children.support'], string $birth = '2016-03-01'): SaveChildSupportHandler
    {
        return new SaveChildSupportHandler(new AllowedPermissions($granted), new RecordingChildrenAudit($this->log), $this->clock, $this->store, $this->access($birth), new ChurchCalendar($this->clock, 'Africa/Johannesburg'));
    }

    /** @param list<string> $granted */
    private function accessHandler(array $granted = ['children.support', 'children.manage']): AccessChildSupportHandler
    {
        return new AccessChildSupportHandler(new AllowedPermissions($granted), new RecordingChildrenAudit($this->log), $this->clock, $this->store, $this->store, new ChurchCalendar($this->clock, 'Africa/Johannesburg'));
    }

    /** @param list<string> $granted */
    private function purgeHandler(array $granted = ['children.support', 'children.supervise']): PurgeExpiredSupportHandler
    {
        return new PurgeExpiredSupportHandler(new AllowedPermissions($granted), new RecordingChildrenAudit($this->log), $this->clock, $this->store, $this->store, $this->access('2016-03-01'), new ChurchCalendar($this->clock, 'Africa/Johannesburg'));
    }

    private function access(string $birth): ParticipantAccess
    {
        return new ParticipantAccess(new RecordingPeople($this->log, [self::CHILD => true, self::OTHER => true], [self::CHILD => $birth, self::OTHER => $birth]), new RecordingChildLocks($this->log));
    }

    private function saveCommand(int $version = 0, string $review = '2026-09-01', string $retain = '2027-09-01'): SaveChildSupportCommand
    {
        return new SaveChildSupportCommand(self::ACTOR, self::CHILD, $version, 'Peanuts', 'Epipen', 'Notes', CalendarDate::parse($review), CalendarDate::parse($retain));
    }

    private function record(): SupportRecord
    {
        return SupportRecord::create(self::CHILD, 'Peanuts', 'Epipen', 'Notes', CalendarDate::parse('2026-09-01'), CalendarDate::parse('2027-09-01'), new DateTimeImmutable('2026-05-01T00:00:00Z'));
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

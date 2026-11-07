<?php

declare(strict_types=1);

namespace App\Tests\Giving\Application;

use App\Giving\Application\Command\CreateFund\CreateFundCommand;
use App\Giving\Application\Command\CreateFund\CreateFundHandler;
use App\Giving\Application\Command\UpdateFund\UpdateFundCommand;
use App\Giving\Application\Command\UpdateFund\UpdateFundHandler;
use App\Giving\Application\GivingAccess;
use App\Giving\Application\GivingHash;
use App\Giving\Application\Query\ListGivingFunds\ListGivingFundsHandler;
use App\Giving\Application\Query\ListGivingFunds\ListGivingFundsQuery;
use App\Giving\Domain\GivingException;
use App\IdentityAccess\Domain\IdentityException;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;

/** Fund handlers in isolation: no database, no transaction, no identity context. */
final class FundHandlersTest extends TestCase
{
    private const string ACTOR = '99999999-9999-4999-8999-999999999999';
    private const string FUND = '00000000-0000-4000-8000-000000000001';
    private const string OTHER = '00000000-0000-4000-8000-000000000002';

    private InMemoryFunds $funds;
    private RecordingHistory $history;
    private RecordingPermissions $permissions;

    protected function setUp(): void
    {
        $this->funds = new InMemoryFunds();
        $this->history = new RecordingHistory();
        $this->permissions = new RecordingPermissions(self::ACTOR, ['giving.manage', 'giving.read']);
    }

    private function create(string $id = self::FUND, ?string $name = 'General', ?string $description = 'Shared needs', string $actor = self::ACTOR): string
    {
        return (new CreateFundHandler(new GivingAccess($this->permissions), $this->funds, $this->history, new MockClock('2026-06-01 10:00:00 UTC')))
            ->handle(new CreateFundCommand($actor, $id, $name, $description));
    }

    private function update(int $version, ?string $name = 'General', bool $active = false, ?string $reason = 'Consolidated funds', string $id = self::FUND): void
    {
        (new UpdateFundHandler(new GivingAccess($this->permissions), $this->funds, $this->history, new MockClock('2026-06-01 10:00:00 UTC')))
            ->handle(new UpdateFundCommand(self::ACTOR, $id, $version, $name, 'Shared needs', $active, $reason));
    }

    public function testCreatingStoresTheFundUnderTheRequestIdWithItsHashAndAuditsIt(): void
    {
        self::assertSame(self::FUND, $this->create(name: ' General '));

        self::assertSame('General', $this->funds->funds[self::FUND]->name);
        self::assertSame(GivingHash::of('fund', self::ACTOR, 'General', 'Shared needs'), $this->funds->hashes[self::FUND]);
        self::assertSame(['FUND:FUND_CREATED:'.self::FUND.':'], $this->history->entries);
        self::assertSame([self::ACTOR], $this->history->actors);
    }

    public function testAnIdenticalRetryReturnsTheSameFundWithoutAuditingAgain(): void
    {
        $this->create();

        self::assertSame(self::FUND, $this->create(name: '  General'));
        self::assertCount(1, $this->funds->funds);
        self::assertCount(1, $this->history->entries);
    }

    public function testReusingARequestIdForDifferentDetailsIsAConflict(): void
    {
        $this->create();

        $this->expectException(GivingException::class);
        $this->expectExceptionMessage('This request identifier was already used for different details.');

        $this->create(description: 'Changed');
    }

    public function testADuplicateNameIgnoringCaseIsAConflict(): void
    {
        $this->create();
        try {
            $this->create(self::OTHER, ' general ');
            self::fail('Expected a duplicate name.');
        } catch (GivingException $e) {
            self::assertSame('A fund already uses this name.', $e->getMessage());
            self::assertCount(1, $this->history->entries);
        }
    }

    public function testCreatingChecksTheCallersPermissionFirst(): void
    {
        $this->permissions = new RecordingPermissions(self::ACTOR, ['giving.read']);
        try {
            $this->create(name: null);
            self::fail('Expected a permission failure.');
        } catch (IdentityException) {
            self::assertSame([self::ACTOR.':giving.manage'], $this->permissions->checks);
            self::assertSame([], $this->funds->funds);
            self::assertSame([], $this->history->entries);
        }
    }

    public function testCreatingRejectsASpoofedActor(): void
    {
        $this->expectException(IdentityException::class);

        $this->create(actor: '88888888-8888-4888-8888-888888888888');
    }

    public function testUpdatingSavesTheNextVersionAndAuditsTheReason(): void
    {
        $this->create();
        $this->update(0, reason: '  Consolidated funds ');

        self::assertSame(1, $this->funds->funds[self::FUND]->version);
        self::assertFalse($this->funds->funds[self::FUND]->active);
        self::assertSame('FUND:FUND_UPDATED:'.self::FUND.':Consolidated funds', $this->history->entries[1]);
        self::assertSame(['fund:'.self::FUND], $this->funds->log->locks);
    }

    public function testUpdatingRejectsAStaleVersionWithoutAuditing(): void
    {
        $this->create();
        try {
            $this->update(3);
            self::fail('Expected a stale version.');
        } catch (GivingException $e) {
            self::assertSame('This record changed. Refresh before trying again.', $e->getMessage());
            self::assertSame(0, $this->funds->funds[self::FUND]->version);
            self::assertCount(1, $this->history->entries);
        }
    }

    public function testUpdatingRequiresAReasonBeforeLockingTheFund(): void
    {
        $this->create();
        try {
            $this->update(0, reason: ' ');
            self::fail('Expected a missing reason.');
        } catch (GivingException $e) {
            self::assertSame('Change reason is required and must be at most 1000 characters.', $e->getMessage());
            self::assertSame([], $this->funds->log->locks);
        }
    }

    public function testUpdatingToAnotherFundsNameIsAConflictWithoutAuditing(): void
    {
        $this->create();
        $this->create(self::OTHER, 'Missions');
        try {
            $this->update(0, name: 'MISSIONS', id: self::FUND);
            self::fail('Expected a duplicate name.');
        } catch (GivingException) {
            self::assertCount(2, $this->history->entries);
        }
    }

    public function testUpdatingAnUnknownFundIsNotFound(): void
    {
        $this->expectException(GivingException::class);
        $this->expectExceptionMessage('The Giving record was not found.');

        $this->update(0);
    }

    public function testUpdatingChecksPermissionBeforeLookingUpTheFund(): void
    {
        $this->permissions = new RecordingPermissions(self::ACTOR, ['giving.read']);
        try {
            $this->update(0, reason: null);
            self::fail('Expected a permission failure.');
        } catch (IdentityException) {
            self::assertSame([], $this->funds->log->locks);
        }
    }

    public function testListingTrimsTheSearchAndTreatsBlankAsNone(): void
    {
        $reader = new FakeGivingReader();
        $list = new ListGivingFundsHandler(new GivingAccess($this->permissions), $reader);

        $list->handle(new ListGivingFundsQuery(true, '  gen ', 10, 5));
        $list->handle(new ListGivingFundsQuery(false, "\u{3000}", 25, 0));
        $list->handle(new ListGivingFundsQuery(false, null, 25, 0));

        self::assertSame([[true, 'gen', 10, 5], [false, '', 25, 0], [false, '', 25, 0]], $reader->calls);
        self::assertSame(['giving.read', 'giving.read', 'giving.read'], $this->permissions->checks);
    }

    public function testListingRejectsAnInvalidPage(): void
    {
        $this->expectException(GivingException::class);
        $this->expectExceptionMessage('Use a page size of 1–100 and an offset of 0–100000.');

        (new ListGivingFundsHandler(new GivingAccess($this->permissions), new FakeGivingReader()))->handle(new ListGivingFundsQuery(false, null, 101, 0));
    }

    public function testListingChecksGivingReadBeforeValidating(): void
    {
        $this->expectException(IdentityException::class);

        (new ListGivingFundsHandler(new GivingAccess(new RecordingPermissions(self::ACTOR, ['giving.manage'])), new FakeGivingReader()))
            ->handle(new ListGivingFundsQuery(false, null, 101, 0));
    }
}

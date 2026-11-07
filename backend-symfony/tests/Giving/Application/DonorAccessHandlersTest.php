<?php

declare(strict_types=1);

namespace App\Tests\Giving\Application;

use App\Giving\Application\Command\SetOnlineFund\SetOnlineFundCommand;
use App\Giving\Application\Command\SetOnlineFund\SetOnlineFundHandler;
use App\Giving\Application\Command\VerifyDonorLink\VerifyDonorLinkCommand;
use App\Giving\Application\Command\VerifyDonorLink\VerifyDonorLinkHandler;
use App\Giving\Application\Contract\OnlineFund;
use App\Giving\Application\GivingAccess;
use App\Giving\Application\Query\ListDonorAccess\ListDonorAccessHandler;
use App\Giving\Application\Query\ListDonorAccess\ListDonorAccessQuery;
use App\Giving\Domain\Fund;
use App\Giving\Domain\GivingException;
use App\IdentityAccess\Domain\IdentityException;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;

/** Donor link, online fund and donor-access handlers in isolation: no database, no transaction, no identity context. */
final class DonorAccessHandlersTest extends TestCase
{
    private const string STAFF = '99999999-9999-4999-8999-999999999999';
    private const string ACCOUNT = '00000000-0000-4000-8000-0000000000a1';
    private const string PERSON = '00000000-0000-4000-8000-0000000000d1';
    private const string OTHER = '00000000-0000-4000-8000-0000000000d2';
    private const string FUND = '00000000-0000-4000-8000-000000000001';

    private LockLog $log;
    private InMemoryDonorLinks $links;
    private FakeAccounts $accounts;
    private FakeMembers $members;
    private FakePeople $people;
    private InMemoryFunds $funds;
    private RecordingHistory $history;
    private RecordingPermissions $permissions;
    private MockClock $clock;

    protected function setUp(): void
    {
        $this->log = new LockLog();
        $this->links = new InMemoryDonorLinks($this->log);
        $this->accounts = new FakeAccounts($this->log);
        $this->accounts->active[self::ACCOUNT] = 'Donor';
        $this->members = new FakeMembers();
        $this->people = new FakePeople($this->log);
        $this->people->add(self::PERSON);
        $this->people->add(self::OTHER);
        $this->funds = new InMemoryFunds($this->log);
        $this->funds->funds[self::FUND] = Fund::create(self::FUND, 'General', '');
        $this->history = new RecordingHistory();
        $this->permissions = new RecordingPermissions(self::STAFF, ['identity.manage', 'giving.manage']);
        $this->clock = new MockClock('2026-02-01 08:00:00 UTC');
    }

    private function link(bool $active = true, int $version = 0, string $person = self::PERSON, string $actor = self::STAFF, ?string $evidence = ' Verified in person '): void
    {
        (new VerifyDonorLinkHandler($this->permissions, $this->accounts, $this->members, $this->accounts, $this->people, $this->links, $this->clock))
            ->handle(new VerifyDonorLinkCommand($actor, self::ACCOUNT, $person, $active, $version, $evidence));
    }

    private function offer(bool $enabled = true, int $version = 0, string $actor = self::STAFF): void
    {
        (new SetOnlineFundHandler(new GivingAccess($this->permissions), $this->funds, $this->history, $this->clock))
            ->handle(new SetOnlineFundCommand($actor, self::FUND, $enabled, $version));
    }

    private static function message(callable $action): string
    {
        try {
            $action();
        } catch (GivingException $error) {
            return $error->getMessage();
        }
        self::fail('the command was accepted');
    }

    public function testVerifyingLocksTheAccountThenTheLinkThenThePersonAndRecordsEvidence(): void
    {
        $this->link();

        self::assertSame(['account:'.self::ACCOUNT, 'link:'.self::ACCOUNT, 'person:'.self::PERSON], $this->log->locks);
        $link = $this->links->links[self::ACCOUNT];
        self::assertSame([self::PERSON, true, 1, self::STAFF], [$link->personId, $link->active, $link->version, $link->verifiedBy]);
        self::assertEquals(new DateTimeImmutable('2026-02-01 08:00:00 UTC'), $link->verifiedAt);
        self::assertSame([self::ACCOUNT.':1:Verified in person'], $this->links->history);
    }

    public function testRevokingAndRestoringAreVersionedAndNeverReassign(): void
    {
        $this->link();
        $this->log->locks = [];
        $this->link(false, 1);
        self::assertSame(['account:'.self::ACCOUNT, 'link:'.self::ACCOUNT], $this->log->locks, 'revoking does not need the person');
        self::assertSame([false, 2], [$this->links->links[self::ACCOUNT]->active, $this->links->links[self::ACCOUNT]->version]);

        self::assertSame('This record changed. Refresh before trying again.', self::message(fn () => $this->link(true, 1)));
        self::assertStringStartsWith('A verified account cannot be reassigned to another donor.', self::message(fn () => $this->link(true, 2, self::OTHER)));
        $this->link(true, 2);
        self::assertSame(3, $this->links->links[self::ACCOUNT]->version);
        self::assertCount(3, $this->links->history);
    }

    public function testANewLinkMustBeActiveAtVersionZero(): void
    {
        self::assertSame('Refresh donor access before trying again.', self::message(fn () => $this->link(true, 1)));
        self::assertSame('Refresh donor access before trying again.', self::message(fn () => $this->link(false, 0)));
        self::assertSame([], $this->links->links);
    }

    public function testTheAccountMustBeActiveAndThePersonAvailable(): void
    {
        $this->people->add(self::PERSON, false);
        self::assertSame('Choose an active account and available person.', self::message(fn () => $this->link()));
        self::assertSame('Choose an active account and available person.', self::message(fn () => $this->link(person: '00000000-0000-4000-8000-0000000000ff')));
        $this->accounts->active = [];
        self::assertSame('Choose an active account and available person.', self::message(fn () => $this->link(person: self::OTHER)));
        self::assertSame([], $this->links->history);
    }

    public function testTheDonorMustMatchTheVerifiedMemberIdentityAndOnePersonHasOneAccount(): void
    {
        $this->members->bound[self::ACCOUNT] = self::OTHER;
        self::assertSame('The donor must match the verified member identity.', self::message(fn () => $this->link()));
        self::assertSame(['account:'.self::ACCOUNT], $this->log->locks, 'checked under the account lock, before the link');
        $this->link(person: self::OTHER);

        $this->links->links = [];
        $this->members->bound = [];
        $this->links->add('00000000-0000-4000-8000-0000000000a2', self::PERSON);
        self::assertStringStartsWith('This fund name, gift reference, reversal or request conflicts', self::message(fn () => $this->link()));
    }

    public function testEvidenceIsRequired(): void
    {
        self::assertSame('Identity verification evidence or revocation reason is required and must be at most 1000 characters.', self::message(fn () => $this->link(evidence: ' ')));
        self::assertSame([], $this->log->locks);
    }

    public function testLinksNeedBothIdentityAndGivingManagementForTheTrustedCaller(): void
    {
        foreach ([[self::STAFF, ['giving.manage']], [self::STAFF, ['identity.manage']], ['00000000-0000-4000-8000-000000000999', ['identity.manage', 'giving.manage']]] as [$actor, $granted]) {
            $this->permissions = new RecordingPermissions(self::STAFF, $granted);
            try {
                $this->link(actor: $actor);
                self::fail('linked without both permissions');
            } catch (IdentityException) {
            }
        }
        self::assertSame([], $this->log->locks);
        self::assertSame([], $this->links->links);
    }

    public function testOnlineFundsAreExplicitVersionedAndAudited(): void
    {
        $this->offer();
        self::assertSame([true, 1], [$this->funds->online[self::FUND], $this->funds->funds[self::FUND]->version]);
        self::assertSame('This record changed. Refresh before trying again.', self::message(fn () => $this->offer(false, 0)));
        $this->offer(false, 1);
        self::assertFalse($this->funds->online[self::FUND]);
        self::assertSame([
            'FUND:ONLINE_GIVING_CHANGED:'.self::FUND.':Offered to donors online.',
            'FUND:ONLINE_GIVING_CHANGED:'.self::FUND.':Removed from online giving.',
        ], $this->history->entries);

        $this->funds->funds[self::FUND] = Fund::reconstitute(self::FUND, 'General', '', false, 2);
        self::assertSame('Activate this fund before offering it online.', self::message(fn () => $this->offer(true, 2)));
        $this->offer(false, 2);
        self::assertSame(3, $this->funds->funds[self::FUND]->version, 'an archived fund can still be withdrawn');
    }

    public function testOnlineFundsNeedGivingManagementAndAKnownFund(): void
    {
        $this->funds->funds = [];
        self::assertSame('The Giving record was not found.', self::message(fn () => $this->offer()));
        $this->permissions = new RecordingPermissions(self::STAFF, ['identity.manage', 'giving.read']);
        $this->log->locks = [];
        try {
            $this->offer();
            self::fail('offered without giving.manage');
        } catch (IdentityException) {
            self::assertSame([self::STAFF.':giving.manage'], $this->permissions->checks);
        }
        self::assertSame([], $this->log->locks);
    }

    public function testDonorAccessListsNamesPagesAndTheFundCatalogue(): void
    {
        $funds = new FakeOnlineFunds();
        $funds->catalogue = [new OnlineFund(self::FUND, 'General', '', true, true, 1)];
        $this->accounts->names = [self::ACCOUNT => 'Donor account'];
        $this->links->add(self::ACCOUNT, self::PERSON);
        $this->links->add('00000000-0000-4000-8000-0000000000a2', '00000000-0000-4000-8000-0000000000ff', false, 2);
        $handler = new ListDonorAccessHandler($this->permissions, $this->links, $funds, $this->people, $this->accounts);

        $page = $handler->handle(new ListDonorAccessQuery(1, 0));
        self::assertTrue($page->hasMore);
        self::assertSame([[self::ACCOUNT, 'Donor account', self::PERSON, 'Donor Mabasa', true, 1]], array_map(static fn ($l): array => [$l->accountId, $l->accountName, $l->personId, $l->personName, $l->active, $l->version], $page->links));
        $second = $handler->handle(new ListDonorAccessQuery(1, 1));
        self::assertFalse($second->hasMore);
        self::assertSame(['Account unavailable', 'Person unavailable', false], [$second->links[0]->accountName, $second->links[0]->personName, $second->links[0]->active]);
        self::assertEquals($funds->catalogue, $page->funds);
        self::assertSame([true, true], $funds->calls, 'the management catalogue includes funds not offered online');

        foreach ([[0, 0], [101, 0], [1, -1], [1, 100001]] as [$limit, $offset]) {
            self::assertSame('Choose a valid page.', self::message(static fn () => $handler->handle(new ListDonorAccessQuery($limit, $offset))));
        }
        $funds->catalogue = array_fill(0, 501, $funds->catalogue[0]);
        self::assertSame('The fund catalogue exceeds this view. Contact support.', self::message(static fn () => $handler->handle(new ListDonorAccessQuery(25, 0))));
    }

    public function testDonorAccessNeedsBothPermissions(): void
    {
        foreach ([['giving.manage'], ['identity.manage']] as $granted) {
            $permissions = new RecordingPermissions(self::STAFF, $granted);
            try {
                (new ListDonorAccessHandler($permissions, $this->links, new FakeOnlineFunds(), $this->people, $this->accounts))->handle(new ListDonorAccessQuery(25, 0));
                self::fail('listed without both permissions');
            } catch (IdentityException) {
            }
        }
        $this->addToAssertionCount(1);
    }
}

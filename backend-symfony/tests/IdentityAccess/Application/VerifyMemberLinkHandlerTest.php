<?php

declare(strict_types=1);

namespace App\Tests\IdentityAccess\Application;

use App\IdentityAccess\Application\Command\VerifyMemberLink\VerifyMemberLinkCommand;
use App\IdentityAccess\Application\Command\VerifyMemberLink\VerifyMemberLinkHandler;
use App\IdentityAccess\Domain\IdentityException;
use App\Tests\Support\AllowedPermissions;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;

/** Linking logic in isolation: lock order, vetoes and optimistic versions, with no database. */
final class VerifyMemberLinkHandlerTest extends TestCase
{
    private const string STAFF = '99999999-9999-4999-8999-999999999999';
    private const string ACCOUNT = '11111111-1111-4111-8111-111111111111';
    private const string PERSON = '22222222-2222-4222-8222-222222222222';
    private const string OTHER = '33333333-3333-4333-8333-333333333333';

    private LockLog $log;
    private FakeLinks $links;

    protected function setUp(): void
    {
        $this->log = new LockLog();
        $this->links = new FakeLinks($this->log);
    }

    public function testVerifyingLocksAccountThenLinkThenPersonAndKeepsTheEvidence(): void
    {
        $this->handler()->handle($this->command(true, 0, '  Met at the welcome desk  '));

        self::assertSame(['account', 'link', 'person'], $this->log->entries);
        self::assertSame(1, $this->links->links[self::ACCOUNT]->version);
        self::assertSame(['Met at the welcome desk'], $this->links->evidence);
    }

    public function testBothStaffPermissionsAreRequiredBeforeAnyLockIsTaken(): void
    {
        foreach ([['identity.manage'], ['people.manage'], []] as $granted) {
            try {
                $this->handler(granted: $granted)->handle($this->command(true, 0));
                self::fail('Expected a permission failure.');
            } catch (IdentityException) {
                self::assertSame([], $this->log->entries);
            }
        }
    }

    public function testEvidenceIsRequiredAndBounded(): void
    {
        foreach (['', '   ', str_repeat('x', 1001)] as $evidence) {
            try {
                $this->handler()->handle($this->command(true, 0, $evidence));
                self::fail('Expected invalid evidence.');
            } catch (IdentityException $error) {
                self::assertSame('Record verification evidence or a revocation reason (at most 1000 characters).', $error->getMessage());
            }
        }
        self::assertSame([], $this->links->links);
    }

    public function testInactiveAccountsUnavailablePeopleAndVetoedBindingsAreRefused(): void
    {
        $cases = [
            'inactive account' => $this->handler(activeAccounts: []),
            'archived or deceased person' => $this->handler(availability: [self::PERSON => false]),
            'unknown person' => $this->handler(availability: []),
            'financial identity veto' => $this->handler(policies: [new FixedPolicy(true), new FixedPolicy(false)]),
        ];
        foreach ($cases as $name => $handler) {
            try {
                $handler->handle($this->command(true, 0));
                self::fail("Accepted: $name");
            } catch (IdentityException $error) {
                self::assertSame('Choose an active account and available person matching existing verified financial identity.', $error->getMessage(), $name);
            }
        }
        self::assertSame([], $this->links->links);
    }

    public function testAFirstLinkMustBeActiveAtVersionZero(): void
    {
        foreach ([[true, 1], [false, 0]] as [$active, $version]) {
            try {
                $this->handler()->handle($this->command($active, $version));
                self::fail('Expected a conflict.');
            } catch (IdentityException $error) {
                self::assertSame('Refresh member access before trying again.', $error->getMessage());
            }
        }
    }

    public function testRevokingAndRestoringKeepsThePersonAndAdvancesTheVersion(): void
    {
        $handler = $this->handler();
        $handler->handle($this->command(true, 0));

        $handler->handle($this->command(false, 1, 'Left the church'));
        self::assertFalse($this->links->links[self::ACCOUNT]->active);
        self::assertSame(2, $this->links->links[self::ACCOUNT]->version);

        $handler->handle($this->command(true, 2, 'Returned'));
        self::assertTrue($this->links->links[self::ACCOUNT]->active);
        self::assertSame(['Verified in person', 'Left the church', 'Returned'], $this->links->evidence);
    }

    public function testRevokingDoesNotRequireThePersonToBeAvailableButReassignmentIsNeverAllowed(): void
    {
        $this->handler()->handle($this->command(true, 0));
        $archived = $this->handler(availability: [self::PERSON => false, self::OTHER => true]);

        $archived->handle($this->command(false, 1, 'Archived'));
        self::assertFalse($this->links->links[self::ACCOUNT]->active);

        $this->expectException(IdentityException::class);
        $this->handler()->handle($this->command(true, 2, 'Switch', person: self::OTHER));
    }

    /**
     * @param list<string>           $granted
     * @param list<string>           $activeAccounts
     * @param array<string, bool>    $availability
     * @param list<FixedPolicy>|null $policies
     */
    private function handler(
        array $granted = ['identity.manage', 'people.manage'],
        array $activeAccounts = [self::ACCOUNT],
        array $availability = [self::PERSON => true, self::OTHER => true],
        ?array $policies = null,
    ): VerifyMemberLinkHandler {
        $accounts = new FakeAccounts($this->log, $activeAccounts);

        return new VerifyMemberLinkHandler(
            new AllowedPermissions($granted),
            $accounts,
            $accounts,
            $this->links,
            new FakePeople($this->log, $availability),
            $policies ?? [new FixedPolicy(true)],
            new MockClock('2026-06-01 10:00:00 UTC'),
        );
    }

    private function command(bool $active, int $version, string $evidence = 'Verified in person', string $person = self::PERSON): VerifyMemberLinkCommand
    {
        return new VerifyMemberLinkCommand(self::STAFF, self::ACCOUNT, $person, $active, $version, $evidence);
    }
}

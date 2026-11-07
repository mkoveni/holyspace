<?php

declare(strict_types=1);

namespace App\Tests\IdentityAccess\Domain;

use App\IdentityAccess\Domain\Account;
use App\IdentityAccess\Domain\AccountManagementPolicy;
use App\IdentityAccess\Domain\IdentityException;
use App\IdentityAccess\Domain\IdentityRules;
use App\Shared\Domain\ErrorKind;
use App\Shared\Domain\Revision;
use App\Shared\Domain\StaleRevisionException;
use DateTimeImmutable;
use DomainException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class AccountTest extends TestCase
{
    private const string ID = '11111111-1111-4111-8111-111111111111';

    public function testRegistrationNormalisesAndStartsActive(): void
    {
        $now = new DateTimeImmutable('2026-01-01T10:00:00Z');
        $account = Account::register(self::ID, '  Grace.Hopper ', ' Grace@Example.TEST ', '  Grace Hopper ', 'hash', $now);

        self::assertSame('grace.hopper', $account->username->value);
        self::assertSame('grace@example.test', $account->email->value);
        self::assertSame('Grace Hopper', $account->displayName->value);
        self::assertTrue($account->isActive());
        self::assertEquals($now, $account->revision->timeline->createdAt);
        self::assertEquals($now, $account->revision->timeline->updatedAt);
    }

    public function testStateChangesReturnNewAggregatesAndKeepIdentity(): void
    {
        $created = new DateTimeImmutable('2026-01-01T10:00:00Z');
        $later = new DateTimeImmutable('2026-01-02T10:00:00Z');
        $original = Account::register(self::ID, 'grace', 'grace@example.test', 'Grace', 'old-hash', $created);

        $disabled = $original->disable(null, $later);
        $renewed = $disabled->changePassword('new-hash', null, $later)->updateProfile('g@example.test', 'G H', null, $later)->enable(null, $later);

        self::assertTrue($original->isActive(), 'the original aggregate is never mutated');
        self::assertFalse($disabled->isActive());
        self::assertSame('new-hash', $renewed->passwordHash->value);
        self::assertSame('g@example.test', $renewed->email->value);
        self::assertSame('grace', $renewed->username->value);
        self::assertEquals($created, $renewed->revision->timeline->createdAt);
        self::assertEquals($later, $renewed->revision->timeline->updatedAt);
        self::assertTrue($renewed->hasSameIdentityAs($original));
    }

    public function testVersionedChangesRejectStaleExpectations(): void
    {
        $now = new DateTimeImmutable('2026-01-01T00:00:00Z');
        $account = Account::register(self::ID, 'grace', 'grace@example.test', 'Grace', 'hash', $now);

        $changed = $account->updateProfile('g@example.test', 'G', 0, $now);

        self::assertSame(1, $changed->revision->version);
        self::assertSame(2, $changed->disable(null, $now)->revision->version, 'null skips the check but still advances');
        $this->expectException(StaleRevisionException::class);
        $changed->enable(0, $now);
    }

    public function testTimelineCannotRunBackwards(): void
    {
        $account = Account::register(self::ID, 'grace', 'grace@example.test', 'Grace', 'hash', new DateTimeImmutable('2026-01-02T00:00:00Z'));

        $this->expectException(DomainException::class);
        $account->disable(null, new DateTimeImmutable('2026-01-01T00:00:00Z'));
    }

    public function testReconstituteRejectsUnknownStatus(): void
    {
        $now = new DateTimeImmutable('2026-01-01T00:00:00Z');

        $this->expectException(IdentityException::class);
        Account::reconstitute(self::ID, 'grace', 'grace@example.test', 'Grace', 'hash', 'banished', Revision::reconstitute(0, $now, $now));
    }

    public function testPasswordHashIsRedactedFromDiagnostics(): void
    {
        $account = Account::register(self::ID, 'grace', 'grace@example.test', 'Grace', 'super-secret-hash', new DateTimeImmutable());

        ob_start();
        var_dump($account->passwordHash);
        self::assertStringNotContainsString('super-secret-hash', (string) ob_get_clean());
    }

    /** @return iterable<string, array{string}> */
    public static function invalidUsernames(): iterable
    {
        yield 'too short' => ['ab'];
        yield 'spaces' => ['a b c'];
        yield 'symbol' => ['grace!'];
        yield 'trailing newline' => ["grace\n"];
        yield 'too long' => [str_repeat('a', 81)];
    }

    #[DataProvider('invalidUsernames')]
    public function testRejectsInvalidUsernames(string $username): void
    {
        // Surrounding whitespace is trimmed by design, so only interior problems count.
        $this->expectException(IdentityException::class);
        IdentityRules::username(str_contains($username, "\n") ? "gra\nce" : $username);
    }

    public function testPasswordRulesCountCharactersAndBytes(): void
    {
        IdentityRules::password('twelve chars');
        IdentityRules::password(str_repeat('é', 36)); // 36 characters, 72 bytes

        foreach (['short', str_repeat('é', 37), '            '] as $bad) {
            try {
                IdentityRules::password($bad);
                self::fail('Expected rejection of '.$bad);
            } catch (IdentityException $error) {
                self::assertSame(ErrorKind::Invalid, $error->kind());
            }
        }
    }

    public function testManagementGrantsImplyReadAndWorkingPermissions(): void
    {
        self::assertSame(
            ['events.manage', 'events.read', 'volunteering.manage', 'volunteering.read', 'volunteering.serve'],
            IdentityRules::effectivePermissions(['volunteering.manage', 'events.manage', 'events.manage']),
        );
    }

    public function testAtLeastOneActiveManagerMustRemain(): void
    {
        AccountManagementPolicy::requireActiveManager(1);

        try {
            AccountManagementPolicy::requireActiveManager(0);
            self::fail('Expected conflict');
        } catch (IdentityException $error) {
            self::assertSame(ErrorKind::Conflict, $error->kind());
        }
    }
}

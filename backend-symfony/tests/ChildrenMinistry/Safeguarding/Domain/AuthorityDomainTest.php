<?php

declare(strict_types=1);

namespace App\Tests\ChildrenMinistry\Safeguarding\Domain;

use App\ChildrenMinistry\Safeguarding\Domain\GuardianAuthority;
use App\ChildrenMinistry\Safeguarding\Domain\PickupAuthorization;
use App\ChildrenMinistry\Shared\Domain\ChildrenException;
use App\Shared\Domain\ErrorKind;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class AuthorityDomainTest extends TestCase
{
    private const string CHILD = '11111111-1111-4111-8111-111111111111';
    private const string ADULT = '22222222-2222-4222-8222-222222222222';
    private const string ID = '33333333-3333-4333-8333-333333333333';
    private const string PROGRAMME = '44444444-4444-4444-8444-444444444444';

    public function testAnAuthorityCoversIntervalsInsideItsWindowOnly(): void
    {
        $authority = $this->authority('2026-06-01T08:00:00Z', '2026-06-01T12:00:00Z');

        self::assertTrue($authority->covers($this->at('2026-06-01T08:00:00Z'), $this->at('2026-06-01T12:00:00Z')), 'the start is inclusive and the end may be matched exactly');
        self::assertTrue($authority->covers($this->at('2026-06-01T09:00:00Z'), $this->at('2026-06-01T10:00:00Z')));
        self::assertFalse($authority->covers($this->at('2026-06-01T07:59:59Z'), $this->at('2026-06-01T10:00:00Z')));
        self::assertFalse($authority->covers($this->at('2026-06-01T09:00:00Z'), $this->at('2026-06-01T12:00:01Z')));
        self::assertFalse($authority->covers($this->at('2026-06-01T12:00:00Z'), $this->at('2026-06-01T12:00:00Z')), 'a starting instant at the end is outside the window');
    }

    public function testARevokedAuthorityCoversNothingAndCannotBeRevokedTwice(): void
    {
        $revoked = $this->authority()->revoke(0, '  Court order  ', $this->at('2026-06-01T09:00:00Z'));

        self::assertSame(1, $revoked->version);
        self::assertSame('Court order', $revoked->revocationReason);
        self::assertFalse($revoked->covers($this->at('2026-06-01T09:00:00Z'), $this->at('2026-06-01T10:00:00Z')));
        $this->assertFailure(ErrorKind::Conflict, 'This record is already revoked.', fn () => $revoked->revoke(1, 'Again', $this->at('2026-06-01T09:30:00Z')));
    }

    public function testRevokingChecksTheVersionAndNeedsAReason(): void
    {
        $this->assertFailure(ErrorKind::Conflict, 'This record changed. Reload before trying again.', fn () => $this->authority()->revoke(3, 'Why', $this->at('2026-06-01T09:00:00Z')));
        $this->assertFailure(ErrorKind::Invalid, 'A value is missing or exceeds its maximum length of 500.', fn () => $this->authority()->revoke(0, '   ', $this->at('2026-06-01T09:00:00Z')));
        $this->assertFailure(ErrorKind::Invalid, 'A value is missing or exceeds its maximum length of 500.', fn () => $this->authority()->revoke(0, str_repeat('x', 501), $this->at('2026-06-01T09:00:00Z')));
    }

    public function testAuthorityNeedsAForwardWindowDistinctPeopleAndEvidence(): void
    {
        $this->assertFailure(ErrorKind::Invalid, 'Validity end must follow its start.', fn () => $this->authority('2026-06-01T10:00:00Z', '2026-06-01T10:00:00Z'));
        $this->assertFailure(ErrorKind::Invalid, 'Child and guardian must be different people.', fn () => GuardianAuthority::verify(self::ID, self::CHILD, self::CHILD, $this->at('2026-06-01T08:00:00Z'), $this->at('2026-06-01T12:00:00Z'), 'Birth certificate', self::ADULT));
        $this->assertFailure(ErrorKind::Invalid, 'A value is missing or exceeds its maximum length of 255.', fn () => $this->authority(evidence: null));
        $this->assertFailure(ErrorKind::Invalid, 'A value is missing or exceeds its maximum length of 255.', fn () => $this->authority(evidence: str_repeat('x', 256)));
    }

    public function testAPickupGrantNeedsAnAuthorityOfTheSameChildCoveringItsWholePeriod(): void
    {
        $authority = $this->authority('2026-06-01T08:00:00Z', '2026-06-01T12:00:00Z');
        $grant = PickupAuthorization::grant(self::ID, self::PROGRAMME, self::CHILD, self::ADULT, $authority, $this->at('2026-06-01T09:00:00Z'), $this->at('2026-06-01T10:00:00Z'), 'ID checked', self::ADULT);

        self::assertSame($authority->id, $grant->authorityId);
        self::assertSame(0, $grant->version);
        $message = 'Pickup permission requires verified guardian authority covering its period.';
        $this->assertFailure(ErrorKind::Invalid, $message, fn () => PickupAuthorization::grant(self::ID, self::PROGRAMME, self::CHILD, self::ADULT, $authority, $this->at('2026-06-01T09:00:00Z'), $this->at('2026-06-01T13:00:00Z'), 'ID', self::ADULT));
        $this->assertFailure(ErrorKind::Invalid, $message, fn () => PickupAuthorization::grant(self::ID, self::PROGRAMME, self::ADULT, self::CHILD, $authority, $this->at('2026-06-01T09:00:00Z'), $this->at('2026-06-01T10:00:00Z'), 'ID', self::ADULT));
        $revoked = $authority->revoke(0, 'Withdrawn', $this->at('2026-06-01T08:30:00Z'));
        $this->assertFailure(ErrorKind::Invalid, $message, fn () => PickupAuthorization::grant(self::ID, self::PROGRAMME, self::CHILD, self::ADULT, $revoked, $this->at('2026-06-01T09:00:00Z'), $this->at('2026-06-01T10:00:00Z'), 'ID', self::ADULT));
    }

    public function testAChildCannotCollectThemselves(): void
    {
        $authority = $this->authority('2026-06-01T08:00:00Z', '2026-06-01T12:00:00Z');

        $this->assertFailure(ErrorKind::Invalid, 'A child cannot collect themselves.', fn () => PickupAuthorization::grant(self::ID, self::PROGRAMME, self::CHILD, self::CHILD, $authority, $this->at('2026-06-01T09:00:00Z'), $this->at('2026-06-01T10:00:00Z'), 'ID', self::ADULT));
    }

    public function testAPickupGrantRevokesLikeAnAuthority(): void
    {
        $grant = PickupAuthorization::grant(self::ID, self::PROGRAMME, self::CHILD, self::ADULT, $this->authority(), $this->at('2026-06-01T09:00:00Z'), $this->at('2026-06-01T10:00:00Z'), 'ID', self::ADULT);

        $revoked = $grant->revoke(0, 'Moved away', $this->at('2026-06-01T09:05:00Z'));

        self::assertFalse($revoked->covers($this->at('2026-06-01T09:10:00Z'), $this->at('2026-06-01T09:20:00Z')));
        self::assertSame(1, $revoked->version);
        $this->assertFailure(ErrorKind::Conflict, 'This record is already revoked.', fn () => $revoked->revoke(1, 'Again', $this->at('2026-06-01T09:06:00Z')));
        $this->assertFailure(ErrorKind::Conflict, 'This record changed. Reload before trying again.', fn () => $grant->revoke(1, 'Stale', $this->at('2026-06-01T09:06:00Z')));
    }

    private function authority(string $from = '2026-06-01T08:00:00Z', string $until = '2026-06-01T12:00:00Z', ?string $evidence = 'Birth certificate'): GuardianAuthority
    {
        return GuardianAuthority::verify(self::ID, self::CHILD, self::ADULT, $this->at($from), $this->at($until), $evidence, self::ADULT);
    }

    private function at(string $time): DateTimeImmutable
    {
        return new DateTimeImmutable($time);
    }

    private function assertFailure(ErrorKind $kind, string $message, callable $action): void
    {
        try {
            $action();
            self::fail('Expected a rejection.');
        } catch (ChildrenException $error) {
            self::assertSame($message, $error->getMessage());
            self::assertSame($kind, $error->kind());
        }
    }
}

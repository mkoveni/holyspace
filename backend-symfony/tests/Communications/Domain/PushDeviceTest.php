<?php

declare(strict_types=1);

namespace App\Tests\Communications\Domain;

use App\Communications\Domain\CommunicationException;
use App\Communications\Domain\PushDevice;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class PushDeviceTest extends TestCase
{
    private const string PROJECT = '11111111-1111-4111-8111-111111111111';

    private static function draft(string $account = 'a1', string $session = 's1', string $token = 'token', ?DateTimeImmutable $now = null): PushDevice
    {
        $now ??= new DateTimeImmutable('2026-11-07T10:00:00Z');

        return PushDevice::register('d1', $account, $session, hash('sha256', 'secret'), hash('sha256', $token), 'nonce:sealed', self::PROJECT, 'ios', $now->modify('+1 day'), $now);
    }

    public function testARegistrationStartsActiveAtRevisionZeroUntilItsLease(): void
    {
        $device = self::draft();

        self::assertSame([PushDevice::ACTIVE, 0], [$device->status, $device->revision]);
        self::assertTrue($device->active(new DateTimeImmutable('2026-11-08T09:59:59Z')));
        self::assertFalse($device->active(new DateTimeImmutable('2026-11-08T10:00:00Z')));
    }

    public function testTheLeaseIsTheEarlierOfTheSessionExpiryAndThirtyDays(): void
    {
        $now = new DateTimeImmutable('2026-11-07T10:00:00Z');

        self::assertEquals(new DateTimeImmutable('2026-11-09T10:00:00Z'), PushDevice::lease(new DateTimeImmutable('2026-11-09T10:00:00Z'), $now));
        self::assertEquals(new DateTimeImmutable('2026-12-07T10:00:00Z'), PushDevice::lease(new DateTimeImmutable('2027-03-01T00:00:00Z'), $now));
    }

    public function testRenewalKeepsTheRevisionOnlyWhenNothingAJobDependsOnChanged(): void
    {
        $device = self::draft();
        $later = new DateTimeImmutable('2026-11-08T10:00:00Z');

        self::assertSame(0, $device->renew(self::draft(now: $later))->revision);
        self::assertSame(1, $device->renew(self::draft(token: 'rotated'))->revision);
        self::assertSame(1, $device->renew(self::draft(session: 's2'))->revision);
        self::assertSame(1, $device->renew(self::draft(account: 'a2'))->revision);
        $revoked = $device->revoke(PushDevice::REVOKED, $later);
        self::assertSame([PushDevice::REVOKED, 1], [$revoked->status, $revoked->revision]);
        $renewed = $revoked->renew(self::draft(now: $later));
        self::assertSame([PushDevice::ACTIVE, 2, $device->secretHash], [$renewed->status, $renewed->revision, $renewed->secretHash]);
    }

    public function testTheInstallationSecretIsComparedNotTrusted(): void
    {
        $device = self::draft();

        self::assertTrue($device->heldBy(hash('sha256', 'secret')));
        self::assertFalse($device->heldBy(hash('sha256', 'other')));
    }

    public function testRegistrationInputRules(): void
    {
        PushDevice::requireValidRegistration(str_repeat('a', 43), 'ExponentPushToken[abcdefghij]', 'android');
        PushDevice::requireValidRegistration(str_repeat('a', 100), 'ExpoPushToken['.str_repeat('x', 200).']', 'ios');
        foreach ([
            [str_repeat('a', 42), 'ExpoPushToken[abcdefghij]', 'ios'],
            [str_repeat('a', 101), 'ExpoPushToken[abcdefghij]', 'ios'],
            [str_repeat('a', 42).'!', 'ExpoPushToken[abcdefghij]', 'ios'],
            [str_repeat('a', 43), 'ExpoPushToken[abcdefghi]', 'ios'],
            [str_repeat('a', 43), 'PushToken[abcdefghij]', 'ios'],
            [str_repeat('a', 43), "ExpoPushToken[abcdefghij]\n", 'ios'],
            [str_repeat('a', 43), 'ExpoPushToken[abcdefghij]', 'web'],
        ] as [$secret, $token, $platform]) {
            try {
                PushDevice::requireValidRegistration($secret, $token, $platform);
                self::fail("$token/$platform must be refused");
            } catch (CommunicationException $expected) {
                self::assertSame('Choose a valid device push registration.', $expected->getMessage());
            }
        }
    }

    public function testDumpsShowNoTokenOrSecret(): void
    {
        $dump = print_r(self::draft(), true);

        self::assertStringNotContainsString(hash('sha256', 'secret'), $dump);
        self::assertStringNotContainsString('nonce:sealed', $dump);
    }

    public function testAnInvalidStoredRowIsRejected(): void
    {
        $this->expectExceptionMessage('Invalid push registration.');
        PushDevice::reconstitute('d1', 'a1', 's1', 'not-a-hash', hash('sha256', 't'), 'x:y', self::PROJECT, 'ios', 'ACTIVE', 0, new DateTimeImmutable(), new DateTimeImmutable());
    }
}

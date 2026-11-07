<?php

declare(strict_types=1);

namespace App\Tests\Giving\Domain;

use App\Giving\Domain\DonorLink;
use App\Giving\Domain\GivingException;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class DonorLinkTest extends TestCase
{
    private const string ACCOUNT = '00000000-0000-4000-8000-0000000000a1';
    private const string PERSON = '00000000-0000-4000-8000-0000000000d1';
    private const string STAFF = '99999999-9999-4999-8999-999999999999';

    private static function message(callable $action): string
    {
        try {
            $action();
        } catch (GivingException $error) {
            return $error->getMessage();
        }
        self::fail('the change was accepted');
    }

    public function testRevocationPreservesIdentityAndRequiresTheCurrentVersion(): void
    {
        $link = DonorLink::verify(self::ACCOUNT, self::PERSON, self::STAFF, new DateTimeImmutable('2026-01-01T08:00:00Z'));
        self::assertSame([true, 1], [$link->active, $link->version]);

        $revoked = $link->revise(self::PERSON, false, 1, self::STAFF, new DateTimeImmutable('2026-01-02T08:00:00Z'));
        self::assertSame([false, 2, self::ACCOUNT, self::PERSON], [$revoked->active, $revoked->version, $revoked->accountId, $revoked->personId]);
        self::assertSame('This record changed. Refresh before trying again.', self::message(static fn () => $revoked->revise(self::PERSON, true, 1, self::STAFF, new DateTimeImmutable())));
        self::assertSame(
            'A verified account cannot be reassigned to another donor. Revoke this link and contact support to correct identity evidence.',
            self::message(static fn () => $revoked->revise('00000000-0000-4000-8000-0000000000d2', true, 2, self::STAFF, new DateTimeImmutable())),
        );
        self::assertTrue($revoked->revise(self::PERSON, true, 2, self::STAFF, new DateTimeImmutable())->active, 'restoring keeps the person');
    }

    public function testALinkNeedsAVersionOfAtLeastOne(): void
    {
        self::assertSame('A donor link requires account, person and verification evidence.', self::message(static fn () => DonorLink::reconstitute(self::ACCOUNT, self::PERSON, true, 0, self::STAFF, new DateTimeImmutable())));
    }
}

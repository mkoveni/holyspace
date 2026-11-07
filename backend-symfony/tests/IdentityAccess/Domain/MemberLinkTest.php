<?php

declare(strict_types=1);

namespace App\Tests\IdentityAccess\Domain;

use App\IdentityAccess\Domain\IdentityException;
use App\IdentityAccess\Domain\MemberLink;
use App\Shared\Domain\ErrorKind;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class MemberLinkTest extends TestCase
{
    private const string ACCOUNT = '11111111-1111-4111-8111-111111111111';
    private const string PERSON = '22222222-2222-4222-8222-222222222222';
    private const string OTHER_PERSON = '33333333-3333-4333-8333-333333333333';
    private const string STAFF = '44444444-4444-4444-8444-444444444444';

    public function testVerifyingStartsActiveAtVersionOne(): void
    {
        $now = new DateTimeImmutable('2026-01-01T10:00:00Z');

        $link = MemberLink::verify(self::ACCOUNT, self::PERSON, self::STAFF, $now);

        self::assertTrue($link->active);
        self::assertSame(1, $link->version);
        self::assertSame(self::STAFF, $link->verifiedBy);
        self::assertEquals($now, $link->verifiedAt);
    }

    public function testRevisingTogglesAccessAndRecordsWhoAndWhen(): void
    {
        $link = MemberLink::verify(self::ACCOUNT, self::PERSON, self::STAFF, new DateTimeImmutable('2026-01-01T10:00:00Z'));
        $later = new DateTimeImmutable('2026-02-01T10:00:00Z');

        $revoked = $link->revise(self::PERSON, false, 1, 'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa', $later);

        self::assertFalse($revoked->active);
        self::assertSame(2, $revoked->version);
        self::assertSame('aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa', $revoked->verifiedBy);
        self::assertTrue($link->active, 'the original is untouched');
        self::assertTrue($revoked->revise(self::PERSON, true, 2, self::STAFF, $later)->active, 'access can be restored for the same person');
    }

    public function testAVerifiedAccountCanNeverBeReassignedOrRevisedFromAStaleVersion(): void
    {
        $link = MemberLink::verify(self::ACCOUNT, self::PERSON, self::STAFF, new DateTimeImmutable());

        foreach ([[self::OTHER_PERSON, 1], [self::PERSON, 0], [self::PERSON, 2]] as [$person, $version]) {
            try {
                $link->revise($person, true, $version, self::STAFF, new DateTimeImmutable());
                self::fail('Expected a conflict.');
            } catch (IdentityException $error) {
                self::assertSame(ErrorKind::Conflict, $error->kind());
            }
        }
    }

    public function testVersionsStartAtOne(): void
    {
        $this->expectException(IdentityException::class);

        MemberLink::reconstitute(self::ACCOUNT, self::PERSON, true, 0, self::STAFF, new DateTimeImmutable());
    }
}

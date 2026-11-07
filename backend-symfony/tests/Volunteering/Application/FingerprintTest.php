<?php

declare(strict_types=1);

namespace App\Tests\Volunteering\Application;

use App\Volunteering\Application\Fingerprint;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class FingerprintTest extends TestCase
{
    public function testMatchesTheJavaLengthPrefixedDigest(): void
    {
        // Computed independently with SHA-256 over big-endian int32 length + UTF-8 bytes per value.
        self::assertSame(
            '1d5c6aa98cb96cb88ab56ec5fd6a1f42062ace6186da32c5c666ff9fa128240d',
            Fingerprint::of('abc', '2026-10-25T08:00:00Z', '2026-10-25T08:00:00.500Z'),
        );
    }

    public function testInstantsFormatLikeJavaInstantToString(): void
    {
        self::assertSame('2026-10-25T08:00:00Z', Fingerprint::instant(new DateTimeImmutable('2026-10-25T10:00:00+02:00')));
        self::assertSame('2026-10-25T08:00:00.500Z', Fingerprint::instant(new DateTimeImmutable('2026-10-25T08:00:00.5Z')));
        self::assertSame('2026-10-25T08:00:00.123456Z', Fingerprint::instant(new DateTimeImmutable('2026-10-25T08:00:00.123456Z')));
    }

    public function testNullIsFingerprintedAsTheWordNullLikeJava(): void
    {
        self::assertSame(Fingerprint::of('null'), Fingerprint::of(null));
    }
}

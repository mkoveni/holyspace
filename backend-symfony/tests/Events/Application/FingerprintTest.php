<?php

declare(strict_types=1);

namespace App\Tests\Events\Application;

use App\Events\Application\Fingerprint;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

/** Golden vectors: SHA-256 over big-endian int32 length + UTF-8 bytes of Java's String.valueOf for each value. */
final class FingerprintTest extends TestCase
{
    public function testNoValuesIsTheEmptyDigest(): void
    {
        self::assertSame('e3b0c44298fc1c149afbf4c8996fb92427ae41e4649b934ca495991b7852b855', Fingerprint::of());
    }

    public function testMatchesTheJavaDigestForTextAndInstants(): void
    {
        self::assertSame(
            '1d5c6aa98cb96cb88ab56ec5fd6a1f42062ace6186da32c5c666ff9fa128240d',
            Fingerprint::of('abc', new DateTimeImmutable('2026-10-25T08:00:00Z'), new DateTimeImmutable('2026-10-25T08:00:00.5Z')),
        );
    }

    public function testRendersNumbersBooleansNullAndMultibyteTextLikeJava(): void
    {
        self::assertSame(
            '9f786e30fef8d7d02e2c3239b7514a2cf6ed848aeaa1285d71a769fdbe78e3a4',
            Fingerprint::of('a', 1, true, false, null, '2026-03-29', 'é', new DateTimeImmutable('2026-10-25T08:00:00.123456Z')),
        );
    }

    public function testLengthPrefixesKeepValueBoundariesDistinct(): void
    {
        self::assertNotSame(Fingerprint::of('ab', 'c'), Fingerprint::of('a', 'bc'));
    }

    public function testInstantsFormatLikeJavaInstantToString(): void
    {
        self::assertSame('2026-10-25T08:00:00Z', Fingerprint::instant(new DateTimeImmutable('2026-10-25T10:00:00+02:00')));
        self::assertSame('2026-10-25T08:00:00.500Z', Fingerprint::instant(new DateTimeImmutable('2026-10-25T08:00:00.5Z')));
        self::assertSame('2026-10-25T08:00:00.123456Z', Fingerprint::instant(new DateTimeImmutable('2026-10-25T08:00:00.123456Z')));
    }
}

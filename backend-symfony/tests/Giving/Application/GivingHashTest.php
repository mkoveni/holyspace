<?php

declare(strict_types=1);

namespace App\Tests\Giving\Application;

use App\Giving\Application\GivingHash;
use App\Giving\Domain\Money;
use App\Shared\Domain\CalendarDate;
use PHPUnit\Framework\TestCase;

/**
 * Golden vectors for Java's GivingHash: SHA-256 over UTF-8 "<UTF-16 length>:<value>" per field, "<null>" for null.
 * PROVISIONAL: computed by an independent implementation of the Java algorithm, not captured from a JVM.
 * Replace with Java-produced vectors before any mixed Java/Symfony deployment (decision D5).
 */
final class GivingHashTest extends TestCase
{
    public function testNoFieldsIsTheEmptyDigest(): void
    {
        self::assertSame('e3b0c44298fc1c149afbf4c8996fb92427ae41e4649b934ca495991b7852b855', GivingHash::of());
    }

    public function testMatchesAFundRequestHash(): void
    {
        self::assertSame(
            'f7039bab251b466ff71f3861c8c17f4ce13a06bc6e933c7d35306df67a5b7e92',
            GivingHash::of('fund', '00000000-0000-0000-0000-000000000001', 'Building Fund', 'Roof repairs'),
        );
    }

    public function testRendersNullMoneyDatesBooleansAndNumbersLikeJava(): void
    {
        self::assertSame(
            'fce30f7a81b0cc55bc2ab807d0261f442bd9543ecf6f4e84b7c896cbcbeeb01e',
            GivingHash::of('gift', 'a', null, Money::of(1500, 'ZAR'), CalendarDate::parse('2026-11-07'), 'CASH', true, false, 42),
        );
    }

    public function testCountsLengthInUtf16CodeUnitsNotBytes(): void
    {
        self::assertSame('1a07c0971c1bef61176a4e493d99aef3078331a44bc50f053e605690728f19f5', GivingHash::of('é'));
        self::assertSame('24faff1aa9a057446f39662a949f80749f313c976c6deb45a8e6cf41b0c80862', GivingHash::of('😀'));
    }

    public function testNullHashesLikeTheLiteralNullMarkerAsInJava(): void
    {
        self::assertSame('431d3183736201cf2a7176c3652c731de47b4b3a5eef12cc27786787ea1efd1f', GivingHash::of(null));
        self::assertSame(GivingHash::of(null), GivingHash::of('<null>'));
    }

    public function testLengthPrefixesKeepFieldBoundariesDistinct(): void
    {
        self::assertSame('430fb1b4ac43316eca81fab27a1930ab8eff8fef6a1dc7903dce44bbc2790dc5', GivingHash::of('ab', 'c'));
        self::assertSame('5310a58788781ab25d5ad7c3f85035824b4eb7bdfa394e0ac2186271472b5492', GivingHash::of('a', 'bc'));
    }
}

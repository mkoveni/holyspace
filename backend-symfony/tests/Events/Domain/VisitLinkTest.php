<?php

declare(strict_types=1);

namespace App\Tests\Events\Domain;

use App\Events\Domain\EventException;
use App\Events\Domain\VisitLink;
use PHPUnit\Framework\TestCase;

final class VisitLinkTest extends TestCase
{
    private const string OCCURRENCE = '0b6d3f0e-5a52-4b8e-9d54-0d1f6f9f2c11';

    public function testRotatingInvalidatesTheEarlierGeneration(): void
    {
        $link = VisitLink::enable(self::OCCURRENCE);
        $rotated = $link->rotated();

        self::assertTrue($link->accepts(1));
        self::assertFalse($rotated->accepts(1));
        self::assertTrue($rotated->accepts(2));
    }

    public function testADisabledLinkAcceptsNothingAndKeepsItsGeneration(): void
    {
        $off = VisitLink::enable(self::OCCURRENCE)->rotated()->disabled();

        self::assertFalse($off->accepts(2));
        self::assertTrue($off->enabled()->accepts(2));
    }

    public function testRotatingAReenabledLinkTurnsItOn(): void
    {
        self::assertTrue(VisitLink::reconstitute(self::OCCURRENCE, false, 4)->rotated()->accepts(5));
    }

    public function testGenerationStartsAtOne(): void
    {
        $this->expectException(EventException::class);
        VisitLink::reconstitute(self::OCCURRENCE, true, 0);
    }
}

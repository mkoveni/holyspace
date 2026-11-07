<?php

declare(strict_types=1);

namespace App\Tests\Shared\Presentation;

use App\Shared\Presentation\Json;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Exception\BadRequestException;

final class JsonInstantTest extends TestCase
{
    public function testParsesUtcAndOffsetInstantsKeepingMicroseconds(): void
    {
        self::assertSame('2026-06-01T10:00:00Z', Json::instant(Json::parseInstant('2026-06-01T10:00:00Z')));
        self::assertSame('2026-06-01T08:00:00.5Z', Json::instant(Json::parseInstant('2026-06-01T10:00:00.5+02:00')));
        self::assertSame('2026-06-01T10:00:00.123456Z', Json::instant(Json::parseInstant('2026-06-01T10:00:00.123456789Z')), 'nanoseconds are truncated to microseconds');
    }

    public function testRejectsAnythingElse(): void
    {
        foreach (['2026-06-01', '2026-06-01T10:00:00', '2026-06-01 10:00:00Z', 'tomorrow', '', '2026-13-01T10:00:00Z'] as $value) {
            try {
                Json::parseInstant($value);
                self::fail("Accepted '$value'.");
            } catch (BadRequestException) {
                $this->addToAssertionCount(1);
            }
        }
    }
}

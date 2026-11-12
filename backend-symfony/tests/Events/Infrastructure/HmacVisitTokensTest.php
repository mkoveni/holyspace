<?php

declare(strict_types=1);

namespace App\Tests\Events\Infrastructure;

use App\Events\Infrastructure\HmacVisitTokens;
use PHPUnit\Framework\TestCase;

final class HmacVisitTokensTest extends TestCase
{
    private const string OCCURRENCE = '0b6d3f0e-5a52-4b8e-9d54-0d1f6f9f2c11';

    public function testATokenRoundTripsToItsOccurrenceAndGeneration(): void
    {
        $tokens = new HmacVisitTokens('secret-one');

        $claim = $tokens->verify($tokens->issue(self::OCCURRENCE, 3));

        self::assertNotNull($claim);
        self::assertSame(self::OCCURRENCE, $claim->occurrenceId);
        self::assertSame(3, $claim->generation);
    }

    public function testTokensAreOneUrlSafeSegmentWithoutDots(): void
    {
        self::assertMatchesRegularExpression('/\A[A-Za-z0-9_-]{70}\z/', (new HmacVisitTokens('s'))->issue(self::OCCURRENCE, 1));
    }

    public function testAForgedOrForeignTokenIsRejected(): void
    {
        $tokens = new HmacVisitTokens('secret-one');
        $token = $tokens->issue(self::OCCURRENCE, 1);
        $flipped = ('A' === $token[40] ? 'B' : 'A');

        self::assertNull($tokens->verify(substr($token, 0, 40).$flipped.substr($token, 41)), 'one character changed');
        self::assertNull($tokens->verify(substr($token, 0, 69).('A' === $token[69] ? 'B' : 'A')));
        self::assertNull((new HmacVisitTokens('secret-two'))->verify($token), 'signed with another secret');
        foreach (['', 'abc', $token.'x', substr($token, 1), $token.'.', 'a.b.c'] as $junk) {
            self::assertNull($tokens->verify($junk), $junk);
        }
    }
}

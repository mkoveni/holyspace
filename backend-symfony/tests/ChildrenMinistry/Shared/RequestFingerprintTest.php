<?php

declare(strict_types=1);

namespace App\Tests\ChildrenMinistry\Shared;

use App\ChildrenMinistry\Shared\Application\RequestFingerprint;
use PHPUnit\Framework\TestCase;

final class RequestFingerprintTest extends TestCase
{
    public function testTheSameValuesAlwaysGiveTheSameSha256(): void
    {
        $fingerprint = RequestFingerprint::of('check-in', 'child-1', 3);

        self::assertSame($fingerprint, RequestFingerprint::of('check-in', 'child-1', 3));
        self::assertMatchesRegularExpression('/\A[0-9a-f]{64}\z/', $fingerprint);
    }

    public function testValueBoundariesAreUnambiguous(): void
    {
        self::assertNotSame(RequestFingerprint::of('ab', 'c'), RequestFingerprint::of('a', 'bc'));
        self::assertNotSame(RequestFingerprint::of('a', ''), RequestFingerprint::of('a'));
        self::assertNotSame(RequestFingerprint::of('1'), RequestFingerprint::of(1, ''));
    }

    public function testPrecomputedVector(): void
    {
        // "5:hello" hashed with SHA-256: Java's RequestFingerprint.of("hello") produces the same digest.
        self::assertSame(hash('sha256', '5:hello'), RequestFingerprint::of('hello'));
    }
}

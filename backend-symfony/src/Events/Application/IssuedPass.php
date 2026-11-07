<?php

declare(strict_types=1);

namespace App\Events\Application;

use DateTimeImmutable;

/** The credential shown once to the member as a QR code. It is never stored, only its hash. */
final readonly class IssuedPass
{
    public function __construct(public string $credential, public DateTimeImmutable $expiresAt)
    {
    }

    public function __debugInfo(): array
    {
        return ['credential' => 'redacted'];
    }
}

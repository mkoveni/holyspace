<?php

declare(strict_types=1);

namespace App\Payments\Application\Command\PrepareMobileCheckout;

use DateTimeImmutable;

/** The one-time browser link the app opens; it is the capability, so it is never logged. */
final readonly class MobileHandoff
{
    public function __construct(public string $url, public DateTimeImmutable $expiresAt)
    {
    }

    /** @return array{redacted: true} */
    public function __debugInfo(): array
    {
        return ['redacted' => true];
    }
}

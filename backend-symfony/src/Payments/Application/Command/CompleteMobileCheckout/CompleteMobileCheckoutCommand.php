<?php

declare(strict_types=1);

namespace App\Payments\Application\Command\CompleteMobileCheckout;

use App\Payments\Application\Contract\HostedCheckout;
use App\Shared\Application\Command;
use SensitiveParameter;

/** @implements Command<HostedCheckout> */
final readonly class CompleteMobileCheckoutCommand implements Command
{
    public function __construct(#[SensitiveParameter] public string $token)
    {
    }

    /** @return array{redacted: true} */
    public function __debugInfo(): array
    {
        return ['redacted' => true];
    }
}

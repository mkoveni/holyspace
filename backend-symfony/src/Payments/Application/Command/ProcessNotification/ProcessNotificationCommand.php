<?php

declare(strict_types=1);

namespace App\Payments\Application\Command\ProcessNotification;

final readonly class ProcessNotificationCommand
{
    /** @param array<string, string> $headers lower-case names */
    public function __construct(public string $provider, public string $body, public array $headers, public string $ip)
    {
    }

    /** @return array{provider: string} */
    public function __debugInfo(): array
    {
        return ['provider' => $this->provider];
    }
}

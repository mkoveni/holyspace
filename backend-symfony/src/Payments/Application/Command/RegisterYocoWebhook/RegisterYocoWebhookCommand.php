<?php

declare(strict_types=1);

namespace App\Payments\Application\Command\RegisterYocoWebhook;

final readonly class RegisterYocoWebhookCommand
{
    public function __construct(public string $actorId, public string $requestId, public string $mode, public int $expectedVersion, public string $reason)
    {
    }
}

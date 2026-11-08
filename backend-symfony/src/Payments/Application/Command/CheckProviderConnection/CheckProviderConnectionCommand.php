<?php

declare(strict_types=1);

namespace App\Payments\Application\Command\CheckProviderConnection;

final readonly class CheckProviderConnectionCommand
{
    public function __construct(public string $actorId, public string $provider, public string $mode, public int $expectedVersion)
    {
    }
}

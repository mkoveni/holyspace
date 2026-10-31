<?php

declare(strict_types=1);

namespace App\Payments\Application\Command\SyncSubscription;

use App\Shared\Application\CQRS\Command;

final readonly class SyncSubscriptionCommand implements Command
{
    public function __construct(public string $id, public int $version)
    {
    }
}

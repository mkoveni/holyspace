<?php

declare(strict_types=1);

namespace App\Payments\Application\Command\CloseWebhookOperation;

use App\Shared\Application\Command;

/** @implements Command<null> */
final readonly class CloseWebhookOperationCommand implements Command
{
    public function __construct(public string $actorId, public string $requestId, public int $expectedVersion, public string $reason)
    {
    }
}

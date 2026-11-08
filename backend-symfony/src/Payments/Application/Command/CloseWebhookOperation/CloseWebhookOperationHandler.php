<?php

declare(strict_types=1);

namespace App\Payments\Application\Command\CloseWebhookOperation;

use App\Payments\Application\YocoWebhookManagement;
use App\Shared\Application\CommandHandler;

/** Closes a reviewed webhook operation, disables new Yoco checkouts and asks for a new connection check. */
final readonly class CloseWebhookOperationHandler implements CommandHandler
{
    public function __construct(private YocoWebhookManagement $service)
    {
    }

    public function handle(CloseWebhookOperationCommand $c): null
    {
        $this->service->close($c->actorId, $c->requestId, $c->expectedVersion, $c->reason);

        return null;
    }
}

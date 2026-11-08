<?php

declare(strict_types=1);

namespace App\Payments\Application\Command\RemoveYocoWebhook;

use App\Payments\Application\WebhookChange;
use App\Payments\Application\YocoWebhookManagement;

/** Provider boundary (several transactions around the Yoco calls): not on the command bus. */
final readonly class RemoveYocoWebhookHandler
{
    public function __construct(private YocoWebhookManagement $service)
    {
    }

    public function handle(RemoveYocoWebhookCommand $c): WebhookChange
    {
        return $this->service->remove($c->actorId, $c->requestId, $c->mode, $c->expectedVersion, $c->reason, $c->subscriptionId);
    }
}

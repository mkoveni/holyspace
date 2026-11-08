<?php

declare(strict_types=1);

namespace App\Payments\Application\Command\RegisterYocoWebhook;

use App\Payments\Application\WebhookChange;
use App\Payments\Application\YocoWebhookManagement;

/** Provider boundary (several transactions around the Yoco call): not on the command bus. */
final readonly class RegisterYocoWebhookHandler
{
    public function __construct(private YocoWebhookManagement $service)
    {
    }

    public function handle(RegisterYocoWebhookCommand $c): WebhookChange
    {
        return $this->service->register($c->actorId, $c->requestId, $c->mode, $c->expectedVersion, $c->reason);
    }
}

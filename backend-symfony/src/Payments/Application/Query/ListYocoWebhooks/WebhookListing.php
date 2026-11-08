<?php

declare(strict_types=1);

namespace App\Payments\Application\Query\ListYocoWebhooks;

use App\Payments\Application\Contract\YocoWebhook;
use App\Payments\Domain\WebhookOperation;

final readonly class WebhookListing
{
    /**
     * @param list<YocoWebhook>      $subscriptions the provider's subscriptions in this environment
     * @param list<WebhookOperation> $pending       never exposes their encrypted secrets
     */
    public function __construct(public array $subscriptions, public array $pending)
    {
    }
}

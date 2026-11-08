<?php

declare(strict_types=1);

namespace App\Payments\Application;

/** Outcome of a Yoco webhook registration or removal request (Java `YocoWebhookManagement.Result`). */
final readonly class WebhookChange
{
    public function __construct(public string $requestId, public string $status, public string $message)
    {
    }
}

<?php

declare(strict_types=1);

namespace App\Payments\Application\Contract;

/** A Yoco webhook subscription as the provider lists it (Java `YocoWebhookReader.Entry`). */
final readonly class YocoWebhook
{
    public function __construct(public string $id, public string $name, public string $url, public string $mode)
    {
    }
}

<?php

declare(strict_types=1);

namespace App\Payments\Infrastructure\Gateway;

/** A provider's current settings and whether new checkouts (and monthly giving) are offered. */
final readonly class ResolvedSettings
{
    public function __construct(public GatewaySettings $settings, public bool $available, public bool $monthly)
    {
    }
}

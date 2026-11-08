<?php

declare(strict_types=1);

namespace App\Payments\Infrastructure\Gateway;

use App\Payments\Application\Contract\MobileCheckoutOrigin;
use App\Payments\Domain\PaymentOrder;

final readonly class ConfiguredMobileCheckoutOrigin implements MobileCheckoutOrigin
{
    public function __construct(private ProviderSettingsSource $configurations)
    {
    }

    public function backend(PaymentOrder $order): string
    {
        return $this->configurations->forOrder($order)->backendBase();
    }
}

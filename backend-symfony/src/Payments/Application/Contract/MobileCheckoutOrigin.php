<?php

declare(strict_types=1);

namespace App\Payments\Application\Contract;

use App\Payments\Domain\PaymentOrder;

interface MobileCheckoutOrigin
{
    /** The public backend origin of the order's own configuration, which serves the one-time handoff. */
    public function backend(PaymentOrder $order): string;
}

<?php

declare(strict_types=1);

namespace App\Payments\Application\Query\ListPaymentProviders;

use App\Payments\Application\Contract\PaymentGateway;
use App\Payments\Application\Contract\PaymentProvider;
use App\Payments\Application\PaymentAccess;

final readonly class ListPaymentProvidersHandler
{
    public function __construct(private PaymentAccess $access, private PaymentGateway $gateway)
    {
    }

    /** @return list<PaymentProvider> */
    public function handle(ListPaymentProvidersQuery $q): array
    {
        $this->access->read();

        return $this->gateway->providers();
    }
}

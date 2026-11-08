<?php

declare(strict_types=1);

namespace App\Payments\Application\Query\GetPayment;

use App\Payments\Application\Contract\PaymentDetail;
use App\Payments\Application\Contract\PaymentReader;
use App\Payments\Application\PaymentAccess;
use App\Payments\Domain\PaymentException;

final readonly class GetPaymentHandler
{
    public function __construct(private PaymentAccess $access, private PaymentReader $reader)
    {
    }

    public function handle(GetPaymentQuery $q): PaymentDetail
    {
        $this->access->read();

        return $this->reader->detail($q->orderId) ?? throw PaymentException::missing();
    }
}

<?php

declare(strict_types=1);

namespace App\Payments\Application\Query\GetPaymentReceipt;

final readonly class ReceiptDelivery
{
    public function __construct(public string $id, public string $status, public bool $captured, public int $version, public string $errorCode)
    {
    }
}

<?php

declare(strict_types=1);

namespace App\Payments\Application\Query\GetPaymentReceipt;

final readonly class GetPaymentReceiptQuery
{
    public function __construct(public string $orderId, public string $chargeId, public ?string $contactId)
    {
    }
}

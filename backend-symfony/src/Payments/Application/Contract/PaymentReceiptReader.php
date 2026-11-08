<?php

declare(strict_types=1);

namespace App\Payments\Application\Contract;

use App\Payments\Domain\PaymentReceipt;

interface PaymentReceiptReader
{
    public function receipt(string $id): ?PaymentReceipt;

    public function forCharge(string $chargeId): ?PaymentReceipt;
}

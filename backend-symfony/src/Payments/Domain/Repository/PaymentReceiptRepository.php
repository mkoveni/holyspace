<?php

declare(strict_types=1);

namespace App\Payments\Domain\Repository;

use App\Payments\Domain\PaymentReceipt;

interface PaymentReceiptRepository
{
    public function insert(PaymentReceipt $receipt): void;
}

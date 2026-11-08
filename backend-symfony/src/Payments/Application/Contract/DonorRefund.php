<?php

declare(strict_types=1);

namespace App\Payments\Application\Contract;

use App\Shared\Domain\CalendarDate;

final readonly class DonorRefund
{
    public function __construct(public int $amountMinor, public CalendarDate $refundedOn, public bool $applied)
    {
    }
}

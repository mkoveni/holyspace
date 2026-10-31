<?php

declare(strict_types=1);

namespace App\Giving\Application\Port;

use App\Giving\Application\ReadModel\OnlineGivingFunds;

interface OnlineGivingLedger extends OnlineGivingFunds
{
    public function funds(): array;

    public function record(string $personId, string $fundId, int $amount, string $reference, string $date): string;

    public function refund(string $giftId, int $amount, string $reference, string $date): void;
}

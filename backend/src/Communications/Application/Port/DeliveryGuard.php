<?php

declare(strict_types=1);

namespace App\Communications\Application\Port;

interface DeliveryGuard
{
    public function suppressionReason(string $campaignId): ?string;
}

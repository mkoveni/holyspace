<?php

declare(strict_types=1);

namespace App\Communications\Application\Port;

interface DeliveryConfiguration
{
    public function configuration(): array;
}

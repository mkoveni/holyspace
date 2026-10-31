<?php

declare(strict_types=1);

namespace App\Payments\Application\Port;

interface ProviderCatalogue
{
    public function providers(): array;
}

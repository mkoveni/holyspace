<?php

declare(strict_types=1);

namespace App\Payments\Application\Contract;

use App\Payments\Domain\ProviderSettings;

final readonly class ProviderSettingsView
{
    /** @param list<ProviderEnvironment> $environments test, then live */
    public function __construct(public ProviderSettings $settings, public array $environments)
    {
    }
}

<?php

declare(strict_types=1);

namespace App\Payments\Application\Query\GetPaymentSettings;

use App\Payments\Application\Contract\ConfigurationChange;
use App\Payments\Application\Contract\ProviderSettingsView;

final readonly class PaymentSettingsOverview
{
    /**
     * @param list<ProviderSettingsView> $providers
     * @param list<ConfigurationChange>  $history
     */
    public function __construct(public bool $encryptionReady, public array $providers, public array $history)
    {
    }
}

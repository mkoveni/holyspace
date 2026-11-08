<?php

declare(strict_types=1);

namespace App\Payments\Application\Query\GetPaymentSettings;

use App\Payments\Application\Contract\PaymentCredentialVault;
use App\Payments\Application\Contract\ProviderConfigurationReader;
use App\Payments\Application\PaymentAccess;

final readonly class GetPaymentSettingsHandler
{
    public function __construct(private PaymentAccess $access, private ProviderConfigurationReader $reader, private PaymentCredentialVault $vault)
    {
    }

    public function handle(GetPaymentSettingsQuery $q): PaymentSettingsOverview
    {
        $this->access->configureRead();

        return new PaymentSettingsOverview($this->vault->ready(), $this->reader->settings(), $this->reader->history());
    }
}

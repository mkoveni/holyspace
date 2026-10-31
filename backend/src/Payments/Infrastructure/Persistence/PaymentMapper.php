<?php

declare(strict_types=1);

namespace App\Payments\Infrastructure\Persistence;

use App\Payments\Domain\Model\OnlineGivingSettings;
use App\Payments\Domain\Model\PaymentCharge;
use App\Payments\Domain\Model\PaymentOrder;
use App\Payments\Domain\Model\PaymentRefund;
use App\Shared\Infrastructure\Persistence\WorkflowDocumentMapper;

final class PaymentMapper extends WorkflowDocumentMapper
{
    protected function types(): array
    {
        return ['orders' => PaymentOrder::class, 'charges' => PaymentCharge::class, 'refunds' => PaymentRefund::class, 'settings' => OnlineGivingSettings::class];
    }
}

<?php

declare(strict_types=1);

namespace App\Payments\Application;

use App\Payments\Application\Contract\PurposeSettlement;
use App\Payments\Domain\PaymentException;
use App\Payments\Domain\PaymentOrder;

/** Resolves the settlement registered for an order's purpose. */
final readonly class PurposeSettlements
{
    /** @var array<string, PurposeSettlement> */
    private array $byPurpose;

    /** @param iterable<PurposeSettlement> $settlements */
    public function __construct(iterable $settlements)
    {
        $map = [];
        foreach ($settlements as $settlement) {
            $map[$settlement->purpose()] = $settlement;
        }
        $this->byPurpose = $map;
    }

    public function for(PaymentOrder $order): PurposeSettlement
    {
        return $this->byPurpose[$order->purpose] ?? throw PaymentException::conflict('No settlement is registered for this payment purpose.');
    }
}

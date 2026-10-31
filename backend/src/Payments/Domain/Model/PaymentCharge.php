<?php

declare(strict_types=1);

namespace App\Payments\Domain\Model;

use App\Shared\Domain\Model\WorkflowRecord;
use App\Shared\Domain\ValueObject\Input;
use InvalidArgumentException;

final class PaymentCharge extends WorkflowRecord
{
    public function __construct(
        public readonly string $orderId,
        public readonly string $providerReference,
        public readonly int $amountMinor,
        public readonly string $receivedOn,
        public readonly ?string $giftId = null,
        public readonly int $refundedMinor = 0,
        string $id = '',
        string $createdAt = '',
        string $updatedAt = '',
        int $version = 0,
    ) {
        parent::__construct($id, $createdAt, $updatedAt, $version);
        Input::id($orderId);
        Input::text($providerReference, 200);
        Input::date($receivedOn);
        if ($amountMinor < 1 || $refundedMinor < 0 || $refundedMinor > $amountMinor) {
            throw new InvalidArgumentException('Invalid charge amount.');
        }
    }

    public function refunded(int $minor): self
    {
        return $this->changed(['refundedMinor' => $this->refundedMinor + $minor]);
    }
}

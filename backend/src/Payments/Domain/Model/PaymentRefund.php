<?php

declare(strict_types=1);

namespace App\Payments\Domain\Model;

use App\Shared\Domain\Model\WorkflowRecord;
use App\Shared\Domain\ValueObject\Input;
use DomainException;
use InvalidArgumentException;

final class PaymentRefund extends WorkflowRecord
{
    public function __construct(public readonly string $orderId, public readonly string $providerReference, public readonly int $amountMinor, public readonly string $receivedOn, public readonly ?string $chargeId = null, public readonly bool $applied = false, string $id = '', string $createdAt = '', string $updatedAt = '', int $version = 0)
    {
        parent::__construct($id, $createdAt, $updatedAt, $version);
        Input::id($orderId);
        Input::text($providerReference, 200);
        Input::date($receivedOn);
        if ($amountMinor < 1 || $amountMinor > 100000000) {
            throw new InvalidArgumentException('Invalid refund amount.');
        }
    }

    public function matchCharge(string $chargeId): self
    {
        Input::id($chargeId);
        if ($this->applied) {
            throw new DomainException('This refund has already been applied.');
        }

        return $this->changed(['chargeId' => $chargeId]);
    }

    public function complete(): self
    {
        return $this->changed(['applied' => true]);
    }
}

<?php

declare(strict_types=1);

namespace App\Giving\Domain\Model;

use App\Giving\Domain\ValueObject\Money;
use App\Shared\Domain\Model\WorkflowRecord;
use App\Shared\Domain\ValueObject\Input;
use DomainException;

final class PledgeAllocation extends WorkflowRecord
{
    public function __construct(
        public readonly string $pledgeId,
        public readonly string $giftId,
        public readonly int $amountMinor,
        public readonly string $currency,
        public readonly string $reference,
        public readonly string $status = 'active',
        public readonly ?string $cancellationReason = null,
        string $id = '',
        string $createdAt = '',
        string $updatedAt = '',
        int $version = 0,
    ) {
        parent::__construct($id, $createdAt, $updatedAt, $version);
        Input::id($pledgeId);
        Input::id($giftId);
        Input::text($reference, 100);
        new Money($amountMinor, $currency);
    }

    public function cancel(string $reason): self
    {
        Input::text($reason, 1000);
        if ('active' !== $this->status) {
            throw new DomainException('Allocation is already cancelled.');
        }

        return $this->changed(['status' => 'cancelled', 'cancellationReason' => $reason]);
    }
}

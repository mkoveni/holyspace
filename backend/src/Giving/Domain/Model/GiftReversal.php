<?php

declare(strict_types=1);

namespace App\Giving\Domain\Model;

use App\Giving\Domain\ValueObject\Money;
use App\Shared\Domain\Model\WorkflowRecord;
use App\Shared\Domain\ValueObject\Input;

final class GiftReversal extends WorkflowRecord
{
    public function __construct(
        public readonly string $giftId,
        public readonly int $amountMinor,
        public readonly string $currency,
        public readonly array $allocations,
        public readonly string $reversedOn,
        public readonly string $reason,
        public readonly ?string $externalReference = null,
        string $id = '',
        string $createdAt = '',
        string $updatedAt = '',
        int $version = 0,
    ) {
        parent::__construct($id, $createdAt, $updatedAt, $version);
        Input::id($giftId);
        Input::date($reversedOn);
        Input::text($reason, 1000);
        new Money($amountMinor, $currency);
    }
}

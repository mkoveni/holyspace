<?php

declare(strict_types=1);

namespace App\Giving\Domain\Model;

use App\Giving\Domain\ValueObject\Money;
use App\Shared\Domain\Model\WorkflowRecord;
use App\Shared\Domain\ValueObject\Input;
use InvalidArgumentException;

final class ReconciliationBatch extends WorkflowRecord
{
    public function __construct(
        public readonly string $reference,
        public readonly array $giftIds,
        public readonly int $expectedAmountMinor,
        public readonly string $currency,
        public readonly string $reconciledOn,
        string $id = '',
        string $createdAt = '',
        string $updatedAt = '',
        int $version = 0,
    ) {
        parent::__construct($id, $createdAt, $updatedAt, $version);
        Input::text($reference, 200);
        Input::date($reconciledOn);
        new Money($expectedAmountMinor, $currency);
        if ([] === $giftIds || count(array_unique($giftIds)) !== count($giftIds)) {
            throw new InvalidArgumentException('Select distinct gifts for reconciliation.');
        }
        foreach ($giftIds as $giftId) {
            Input::id($giftId);
        }
    }
}

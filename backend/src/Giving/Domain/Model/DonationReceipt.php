<?php

declare(strict_types=1);

namespace App\Giving\Domain\Model;

use App\Giving\Domain\ValueObject\Money;
use App\Shared\Domain\Model\WorkflowRecord;
use App\Shared\Domain\ValueObject\Input;

final class DonationReceipt extends WorkflowRecord
{
    public function __construct(
        public readonly string $giftId,
        public readonly string $receiptNumber,
        public readonly int $amountMinor,
        public readonly string $currency,
        public readonly string $issuedOn,
        public readonly ?string $donorId = null,
        public readonly bool $void = false,
        string $id = '',
        string $createdAt = '',
        string $updatedAt = '',
        int $version = 0,
    ) {
        parent::__construct($id, $createdAt, $updatedAt, $version);
        Input::id($giftId);
        Input::text($receiptNumber);
        Input::date($issuedOn);
        new Money($amountMinor, $currency);
    }

    public function voidReceipt(): self
    {
        return $this->changed(['void' => true]);
    }
}

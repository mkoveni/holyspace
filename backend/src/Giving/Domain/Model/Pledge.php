<?php

declare(strict_types=1);

namespace App\Giving\Domain\Model;

use App\Giving\Domain\ValueObject\Money;
use App\Shared\Domain\Model\WorkflowRecord;
use App\Shared\Domain\ValueObject\Input;
use InvalidArgumentException;

final class Pledge extends WorkflowRecord
{
    public function __construct(
        public readonly string $donorId,
        public readonly string $fundId,
        public readonly int $amountMinor,
        public readonly string $currency,
        public readonly string $dueOn,
        public readonly string $status = 'open',
        string $id = '',
        string $createdAt = '',
        string $updatedAt = '',
        int $version = 0,
    ) {
        parent::__construct($id, $createdAt, $updatedAt, $version);
        Input::id($donorId);
        Input::id($fundId);
        Input::date($dueOn);
        new Money($amountMinor, $currency);
        if (!in_array($status, ['open', 'fulfilled', 'cancelled'], true)) {
            throw new InvalidArgumentException('Invalid pledge status.');
        }
    }

    public function changeStatus(string $status): self
    {
        return $this->changed(['status' => $status]);
    }
}

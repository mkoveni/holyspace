<?php

declare(strict_types=1);

namespace App\Giving\Domain\Model;

use App\Giving\Domain\ValueObject\Money;
use App\Shared\Domain\Model\WorkflowRecord;
use App\Shared\Domain\ValueObject\Input;
use DomainException;
use InvalidArgumentException;

final class Gift extends WorkflowRecord
{
    public function __construct(
        public readonly int $amountMinor,
        public readonly string $currency,
        public readonly array $allocations,
        public readonly string $receivedOn,
        public readonly string $method,
        public readonly string $idempotencyKey,
        public readonly ?string $donorId = null,
        public readonly ?string $occurrenceId = null,
        public readonly ?string $externalReference = null,
        public readonly string $status = 'posted',
        string $id = '',
        string $createdAt = '',
        string $updatedAt = '',
        int $version = 0,
    ) {
        parent::__construct($id, $createdAt, $updatedAt, $version);
        new Money($amountMinor, $currency);
        Input::date($receivedOn);
        Input::text($idempotencyKey, 200);
        if (!in_array($method, ['cash', 'bank', 'other', 'online'], true) || !in_array($status, ['posted', 'reversed'], true)) {
            throw new InvalidArgumentException('Invalid donation method or status.');
        }
        if (null !== $donorId) {
            Input::id($donorId);
        }
        if (null !== $occurrenceId) {
            Input::id($occurrenceId);
        }
        if (null !== $externalReference) {
            Input::text($externalReference, 200);
        }
        $total = 0;
        $seen = [];
        foreach ($allocations as $allocation) {
            if (!is_array($allocation) || !is_string($allocation['fundId'] ?? null) || !is_int($allocation['amountMinor'] ?? null) || $allocation['amountMinor'] < 1) {
                throw new InvalidArgumentException('Each allocation needs a fund and a positive whole amount.');
            }
            Input::id($allocation['fundId']);
            if (isset($seen[$allocation['fundId']])) {
                throw new InvalidArgumentException('Allocate to each fund only once.');
            }
            $seen[$allocation['fundId']] = true;
            $total += $allocation['amountMinor'];
        }
        if ($total !== $amountMinor) {
            throw new InvalidArgumentException('Fund allocations must equal the entire gift amount.');
        }
    }

    public function reverse(): self
    {
        if ('posted' !== $this->status) {
            throw new DomainException('Gift has already been reversed.');
        }

        return $this->changed(['status' => 'reversed']);
    }
}

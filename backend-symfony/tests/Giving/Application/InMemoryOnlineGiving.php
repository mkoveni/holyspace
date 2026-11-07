<?php

declare(strict_types=1);

namespace App\Tests\Giving\Application;

use App\Giving\Domain\GivingException;
use App\Giving\Domain\OnlineRefund;
use App\Giving\Domain\PledgeAssignment;
use App\Giving\Domain\Repository\OnlineGivingRepository;
use App\Shared\Domain\CalendarDate;

/** In-memory test double for Payments evidence; reads assignments from the pledge fake, as the store reads the shared table. */
final class InMemoryOnlineGiving implements OnlineGivingRepository
{
    /** @var array<string, array{charge: string, reference: string}> */
    public array $sources = [];
    /** @var array<string, OnlineRefund> */
    public array $refunds = [];

    public function __construct(private readonly InMemoryPledges $pledges)
    {
    }

    public function source(string $giftId, string $chargeId, string $reference): void
    {
        $this->sources[$giftId] = ['charge' => $chargeId, 'reference' => $reference];
    }

    public function refundExists(string $id, string $giftId, int $amountMinor, string $reference, CalendarDate $refundedOn): bool
    {
        $refund = $this->refunds[$id] ?? null;
        if (null === $refund) {
            return false;
        }
        if ($refund->giftId !== $giftId || $refund->amountMinor !== $amountMinor || $refund->reference !== $reference || !$refund->refundedOn->equals($refundedOn)) {
            throw GivingException::conflict('Refund identity conflicts with existing evidence.');
        }

        return true;
    }

    public function refund(OnlineRefund $refund): void
    {
        $this->refunds[$refund->id] = $refund;
    }

    public function activeAssignments(string $giftId): array
    {
        $active = array_values(array_filter(
            $this->pledges->assignments,
            static fn (PledgeAssignment $a): bool => $a->giftId === $giftId && PledgeAssignment::ACTIVE === $a->status,
        ));
        usort($active, static fn (PledgeAssignment $a, PledgeAssignment $b): int => [$a->pledgeId, $a->id] <=> [$b->pledgeId, $b->id]);

        return $active;
    }
}

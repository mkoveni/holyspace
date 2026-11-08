<?php

declare(strict_types=1);

namespace App\Tests\Payments\Support;

use App\Giving\Application\Contract\OnlineDonor;
use App\Giving\Application\Contract\OnlineGivingLedger;
use App\Giving\Application\OnlineGivingService;
use App\Giving\Domain\GivingException;
use App\Shared\Domain\CalendarDate;

/** The real Giving ledger, except that the next $failPosts posts fail (Java's spied ledger with doThrow().doCallRealMethod()). */
final class FailingGivingLedger implements OnlineGivingLedger
{
    public int $failPosts = 0;

    public function __construct(private readonly OnlineGivingService $real)
    {
    }

    public function validate(string $donorId, string $fundId): OnlineDonor
    {
        return $this->real->validate($donorId, $fundId);
    }

    public function post(string $chargeId, string $donorId, string $fundId, int $amountMinor, string $reference, CalendarDate $receivedOn, string $initiatedBy): string
    {
        if ($this->failPosts > 0) {
            --$this->failPosts;
            throw GivingException::conflict('Temporary ledger conflict');
        }

        return $this->real->post($chargeId, $donorId, $fundId, $amountMinor, $reference, $receivedOn, $initiatedBy);
    }

    public function refund(string $refundId, string $giftId, int $amountMinor, string $reference, CalendarDate $refundedOn, string $initiatedBy): void
    {
        $this->real->refund($refundId, $giftId, $amountMinor, $reference, $refundedOn, $initiatedBy);
    }
}

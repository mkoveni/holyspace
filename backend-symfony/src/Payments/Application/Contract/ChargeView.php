<?php

declare(strict_types=1);

namespace App\Payments\Application\Contract;

use App\Payments\Domain\Charge;
use App\Payments\Domain\SettlementReview;

/** A charge with its applied refunds and latest settlement review (Java `PaymentReader.ChargeView`). */
final readonly class ChargeView
{
    public bool $needsReview;
    public string $givingStatus;
    public string $settlementStatus;

    public function __construct(public Charge $charge, public int $refundedMinor, public ?SettlementReview $settlement)
    {
        $unmatched = null === $settlement || !$settlement->matches($charge, $refundedMinor);
        $this->needsReview = !$charge->isTest() && $unmatched;
        $this->givingStatus = $charge->isTest() ? 'SIMULATION' : (null === $charge->giftId ? 'POSTING_PENDING' : 'POSTED');
        $this->settlementStatus = $charge->isTest() ? 'NOT_APPLICABLE' : ($unmatched ? 'NEEDS_REVIEW' : 'MATCHED');
    }
}

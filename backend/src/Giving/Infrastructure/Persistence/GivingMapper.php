<?php

declare(strict_types=1);

namespace App\Giving\Infrastructure\Persistence;

use App\Giving\Domain\Model\DonationReceipt;
use App\Giving\Domain\Model\Fund;
use App\Giving\Domain\Model\Gift;
use App\Giving\Domain\Model\GiftReversal;
use App\Giving\Domain\Model\Pledge;
use App\Giving\Domain\Model\PledgeAllocation;
use App\Giving\Domain\Model\ReconciliationBatch;
use App\Shared\Infrastructure\Persistence\WorkflowDocumentMapper;

final class GivingMapper extends WorkflowDocumentMapper
{
    protected function types(): array
    {
        return ['pledge_allocations' => PledgeAllocation::class, 'funds' => Fund::class, 'gifts' => Gift::class, 'receipts' => DonationReceipt::class, 'reversals' => GiftReversal::class, 'pledges' => Pledge::class, 'batches' => ReconciliationBatch::class];
    }
}

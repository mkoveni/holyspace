<?php

declare(strict_types=1);

namespace App\Giving\Infrastructure\ReadModel;

use App\Giving\Application\Command\RecordGift\RecordGiftCommand;
use App\Giving\Application\Command\RecordGift\RecordGiftHandler;
use App\Giving\Application\Command\RecordOnlineRefund\RecordOnlineRefundCommand;
use App\Giving\Application\Command\RecordOnlineRefund\RecordOnlineRefundHandler;
use App\Giving\Application\Port\OnlineGivingLedger;
use App\Giving\Application\ReadModel\OnlineGivingFunds;
use App\Giving\Domain\Repository\GivingRepository;
use Symfony\Component\DependencyInjection\Attribute\AsAlias;

#[AsAlias(OnlineGivingLedger::class)]
#[AsAlias(OnlineGivingFunds::class)]
final readonly class OnlineGivingPosting implements OnlineGivingLedger
{
    public function __construct(
        private GivingRepository $records,
        private RecordGiftHandler $recordGiftHandler,
        private RecordOnlineRefundHandler $recordOnlineRefundHandler,
    ) {
    }

    public function funds(): array
    {
        return array_map(static fn ($fund) => ['id' => $fund->id, 'name' => $fund->name], array_values(array_filter($this->records->all('funds'), static fn ($fund) => $fund->active)));
    }

    public function record(string $personId, string $fundId, int $amount, string $reference, string $date): string
    {
        return ($this->recordGiftHandler)(new RecordGiftCommand(['amountMinor' => $amount, 'currency' => 'ZAR', 'allocations' => [['fundId' => $fundId, 'amountMinor' => $amount]], 'receivedOn' => $date, 'method' => 'online', 'idempotencyKey' => $reference, 'externalReference' => $reference, 'donorId' => $personId]))->id;
    }

    public function refund(string $giftId, int $amount, string $reference, string $date): void
    {
        ($this->recordOnlineRefundHandler)(new RecordOnlineRefundCommand($giftId, $amount, $reference, $date));
    }
}

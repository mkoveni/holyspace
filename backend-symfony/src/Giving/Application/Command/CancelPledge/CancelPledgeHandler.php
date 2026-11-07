<?php

declare(strict_types=1);

namespace App\Giving\Application\Command\CancelPledge;

use App\Giving\Application\GivingAccess;
use App\Giving\Domain\GivingException;
use App\Giving\Domain\Repository\GivingHistoryRepository;
use App\Giving\Domain\Repository\PledgeRepository;
use App\Shared\Application\CommandHandler;
use Psr\Clock\ClockInterface;

/** Cancels an open pledge; its assignments and the financial evidence stay. One lock: the pledge row. */
final readonly class CancelPledgeHandler implements CommandHandler
{
    public function __construct(
        private GivingAccess $access,
        private PledgeRepository $pledges,
        private GivingHistoryRepository $history,
        private ClockInterface $clock,
    ) {
    }

    public function handle(CancelPledgeCommand $command): void
    {
        $this->access->manage($command->actorId);

        $pledge = $this->pledges->findForUpdate($command->pledgeId) ?? throw GivingException::missing();
        $cancelled = $pledge->cancel($command->expectedVersion, $command->reason);
        $this->pledges->save($cancelled);
        $this->history->append($pledge->id, GivingHistoryRepository::PLEDGE, 'PLEDGE_CANCELLED', (string) $cancelled->cancellationReason, $command->actorId, $this->clock->now());
    }
}

<?php

declare(strict_types=1);

namespace App\Giving\Application\Command\UpdateFund;

use App\Giving\Application\GivingAccess;
use App\Giving\Domain\GivingException;
use App\Giving\Domain\GivingRules;
use App\Giving\Domain\Repository\FundRepository;
use App\Giving\Domain\Repository\GivingHistoryRepository;
use App\Shared\Application\CommandHandler;
use Psr\Clock\ClockInterface;

/** Renames, describes or archives a fund; past allocations keep pointing at it. */
final readonly class UpdateFundHandler implements CommandHandler
{
    public function __construct(
        private GivingAccess $access,
        private FundRepository $funds,
        private GivingHistoryRepository $history,
        private ClockInterface $clock,
    ) {
    }

    public function handle(UpdateFundCommand $command): void
    {
        $this->access->manage($command->actorId);
        $reason = GivingRules::text($command->reason, 1000, 'Change reason');

        $current = $this->funds->findForUpdate($command->fundId) ?? throw GivingException::missing();
        $this->funds->save($current->revise($command->expectedVersion, $command->name, $command->description, $command->active));
        $this->history->append($current->id, GivingHistoryRepository::FUND, 'FUND_UPDATED', $reason, $command->actorId, $this->clock->now());
    }
}

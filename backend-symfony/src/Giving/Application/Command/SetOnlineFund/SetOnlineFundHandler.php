<?php

declare(strict_types=1);

namespace App\Giving\Application\Command\SetOnlineFund;

use App\Giving\Application\GivingAccess;
use App\Giving\Domain\GivingException;
use App\Giving\Domain\Repository\FundRepository;
use App\Giving\Domain\Repository\GivingHistoryRepository;
use App\Shared\Application\CommandHandler;
use Psr\Clock\ClockInterface;

/** Offers a fund to donor self-service or withdraws it. Bumps the fund version, so a stale screen is refused. */
final readonly class SetOnlineFundHandler implements CommandHandler
{
    public function __construct(
        private GivingAccess $access,
        private FundRepository $funds,
        private GivingHistoryRepository $history,
        private ClockInterface $clock,
    ) {
    }

    public function handle(SetOnlineFundCommand $command): void
    {
        $this->access->manage($command->actorId);

        $fund = $this->funds->findForUpdate($command->fundId) ?? throw GivingException::missing();
        if ($command->enabled && !$fund->active) {
            throw GivingException::invalid('Activate this fund before offering it online.');
        }
        $this->funds->save($fund->revise($command->expectedVersion, $fund->name, $fund->description, $fund->active));
        $this->funds->offerOnline($fund->id, $command->enabled);
        $this->history->append(
            $fund->id,
            GivingHistoryRepository::FUND,
            'ONLINE_GIVING_CHANGED',
            $command->enabled ? 'Offered to donors online.' : 'Removed from online giving.',
            $command->actorId,
            $this->clock->now(),
        );
    }
}

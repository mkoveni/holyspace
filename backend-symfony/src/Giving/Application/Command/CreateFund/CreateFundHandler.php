<?php

declare(strict_types=1);

namespace App\Giving\Application\Command\CreateFund;

use App\Giving\Application\GivingAccess;
use App\Giving\Application\GivingHash;
use App\Giving\Domain\Fund;
use App\Giving\Domain\GivingException;
use App\Giving\Domain\Repository\FundRepository;
use App\Giving\Domain\Repository\GivingHistoryRepository;
use App\Shared\Application\CommandHandler;
use Psr\Clock\ClockInterface;

/** Idempotent on the request id: an identical retry returns the same fund, different details are a conflict. */
final readonly class CreateFundHandler implements CommandHandler
{
    public function __construct(
        private GivingAccess $access,
        private FundRepository $funds,
        private GivingHistoryRepository $history,
        private ClockInterface $clock,
    ) {
    }

    /** @return string the fund id, which is the request id */
    public function handle(CreateFundCommand $command): string
    {
        $this->access->manage($command->actorId);

        $fund = Fund::create($command->requestId, $command->name, $command->description);
        $hash = GivingHash::of('fund', $command->actorId, $fund->name, $fund->description);
        if (null !== $this->funds->createdBy($fund->id, $hash)) {
            return $fund->id;
        }
        if ($this->funds->insert($fund, $hash)) {
            $this->history->append($fund->id, GivingHistoryRepository::FUND, 'FUND_CREATED', '', $command->actorId, $this->clock->now());
        } elseif (null === $this->funds->createdBy($fund->id, $hash)) {
            throw GivingException::conflict('A fund already uses this name.');
        }

        return $fund->id;
    }
}

<?php

declare(strict_types=1);

namespace App\Giving\Application\Command\CreatePledge;

use App\Giving\Application\GivingAccess;
use App\Giving\Application\GivingHash;
use App\Giving\Domain\GivingException;
use App\Giving\Domain\Money;
use App\Giving\Domain\Pledge;
use App\Giving\Domain\Repository\FundRepository;
use App\Giving\Domain\Repository\GivingHistoryRepository;
use App\Giving\Domain\Repository\PledgeRepository;
use App\People\Application\Contract\PersonDirectory;
use App\Shared\Application\CommandHandler;
use Psr\Clock\ClockInterface;

/**
 * Records a donor's commitment. Idempotent on the request id: an identical retry returns the same pledge, different
 * details are a conflict. Lock order: donor person row, then the fund.
 */
final readonly class CreatePledgeHandler implements CommandHandler
{
    public function __construct(
        private GivingAccess $access,
        private PledgeRepository $pledges,
        private FundRepository $funds,
        private GivingHistoryRepository $history,
        private PersonDirectory $people,
        private ClockInterface $clock,
    ) {
    }

    /** @return string the pledge id, which is the request id */
    public function handle(CreatePledgeCommand $command): string
    {
        $this->access->manage($command->actorId);
        $now = $this->clock->now();
        $pledge = Pledge::create(
            $command->requestId,
            $command->donorId,
            $command->fundId,
            Money::of($command->amountMinor, (string) $command->currency),
            $command->startsOn,
            $command->endsOn,
            $command->actorId,
            $now,
        );
        $hash = GivingHash::of('pledge', $command->actorId, $pledge->donorId, $pledge->fundId, $pledge->target, $pledge->startsOn, $pledge->endsOn);

        $replay = $this->pledges->pledgedBy($pledge->id, $hash);
        if (null !== $replay) {
            return $replay;
        }
        if (true !== $this->people->lock($pledge->donorId)?->available) {
            throw GivingException::invalid('Choose an available donor.');
        }
        if (true !== $this->funds->findForUpdate($pledge->fundId)?->active) {
            throw GivingException::conflict('Choose an active fund.');
        }
        // A concurrent identical request may have committed while this one waited for the locks.
        $replay = $this->pledges->pledgedBy($pledge->id, $hash);
        if (null !== $replay) {
            return $replay;
        }
        $this->pledges->insert($pledge, $hash);
        $this->history->append($pledge->id, GivingHistoryRepository::PLEDGE, 'PLEDGE_CREATED', '', $command->actorId, $now);

        return $pledge->id;
    }
}

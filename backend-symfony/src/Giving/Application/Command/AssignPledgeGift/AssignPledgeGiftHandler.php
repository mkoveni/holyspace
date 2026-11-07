<?php

declare(strict_types=1);

namespace App\Giving\Application\Command\AssignPledgeGift;

use App\Giving\Application\GivingAccess;
use App\Giving\Application\GivingHash;
use App\Giving\Domain\GivingException;
use App\Giving\Domain\GivingRules;
use App\Giving\Domain\Repository\GiftRepository;
use App\Giving\Domain\Repository\GivingHistoryRepository;
use App\Giving\Domain\Repository\PledgeRepository;
use App\Shared\Application\CommandHandler;
use Psr\Clock\ClockInterface;

/**
 * Credits part of a gift's allocation to a pledge. Idempotent on the request id, checked before the version so a retry
 * still succeeds after its own write advanced the pledge. Lock order: the gift row, then the pledge row (the same order as
 * the `giving_assignment_guard` trigger), so two pledges cannot both spend one allocation.
 */
final readonly class AssignPledgeGiftHandler implements CommandHandler
{
    public function __construct(
        private GivingAccess $access,
        private GiftRepository $gifts,
        private PledgeRepository $pledges,
        private GivingHistoryRepository $history,
        private ClockInterface $clock,
    ) {
    }

    /** @return string the assignment id, which is the request id */
    public function handle(AssignPledgeGiftCommand $command): string
    {
        $this->access->manage($command->actorId);
        $hash = GivingHash::of('pledge-assignment', $command->actorId, $command->pledgeId, $command->giftId, $command->expectedVersion, $command->amountMinor);

        $gift = $this->gifts->findForUpdate($command->giftId) ?? throw GivingException::missing();
        $pledge = $this->pledges->findForUpdate($command->pledgeId) ?? throw GivingException::missing();
        $replay = $this->pledges->assignedBy($command->requestId, $hash);
        if (null !== $replay) {
            return $replay;
        }
        GivingRules::version($pledge->version, $command->expectedVersion);
        if ($this->gifts->hasReversal($gift->id)) {
            throw GivingException::conflict('A reversed gift cannot fulfil a pledge.');
        }
        $allocated = 0;
        foreach ($gift->allocations as $allocation) {
            if ($allocation->fundId === $pledge->fundId) {
                $allocated += $allocation->amountMinor;
            }
        }
        $available = $allocated - $this->gifts->refunded($gift->id) - $this->pledges->assigned($gift->id, $pledge->fundId);
        $now = $this->clock->now();
        $assignment = $pledge->assign($gift, $command->requestId, $command->amountMinor, $this->pledges->credited($pledge->id), $available, $command->actorId, $now);
        $this->pledges->insertAssignment($assignment, $hash);
        $this->pledges->save($pledge->revised());
        $this->history->append($pledge->id, GivingHistoryRepository::PLEDGE, 'GIFT_ASSIGNED', '', $command->actorId, $now);

        return $assignment->id;
    }
}

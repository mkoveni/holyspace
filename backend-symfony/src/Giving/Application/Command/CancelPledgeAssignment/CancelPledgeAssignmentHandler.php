<?php

declare(strict_types=1);

namespace App\Giving\Application\Command\CancelPledgeAssignment;

use App\Giving\Application\GivingAccess;
use App\Giving\Domain\GivingException;
use App\Giving\Domain\Repository\GiftRepository;
use App\Giving\Domain\Repository\GivingHistoryRepository;
use App\Giving\Domain\Repository\PledgeRepository;
use App\Shared\Application\CommandHandler;
use Psr\Clock\ClockInterface;

/**
 * Cancels an assignment, releasing its share of the gift allocation; the evidence stays with who cancelled it and why.
 * Lock order as for assigning: the gift row, then the pledge row; the assignment is re-read under those locks.
 */
final readonly class CancelPledgeAssignmentHandler implements CommandHandler
{
    public function __construct(
        private GivingAccess $access,
        private GiftRepository $gifts,
        private PledgeRepository $pledges,
        private GivingHistoryRepository $history,
        private ClockInterface $clock,
    ) {
    }

    public function handle(CancelPledgeAssignmentCommand $command): void
    {
        $this->access->manage($command->actorId);

        $reference = $this->pledges->findAssignment($command->assignmentId) ?? throw GivingException::missing();
        $this->gifts->findForUpdate($reference->giftId) ?? throw GivingException::missing();
        $pledge = $this->pledges->findForUpdate($reference->pledgeId) ?? throw GivingException::missing();
        $current = $this->pledges->findAssignment($command->assignmentId) ?? throw GivingException::missing();
        $now = $this->clock->now();
        $cancelled = $current->cancel($command->expectedVersion, $command->reason, $command->actorId, $now);
        $this->pledges->saveAssignment($cancelled);
        $this->pledges->save($pledge->revised());
        $this->history->append($pledge->id, GivingHistoryRepository::PLEDGE, 'ASSIGNMENT_CANCELLED', (string) $cancelled->cancellationReason, $command->actorId, $now);
    }
}

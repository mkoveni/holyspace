<?php

declare(strict_types=1);

namespace App\Giving\Application\Command\ReviewReconciliation;

use App\Giving\Application\GivingAccess;
use App\Giving\Application\GivingHash;
use App\Giving\Domain\GivingException;
use App\Giving\Domain\GivingRules;
use App\Giving\Domain\Repository\GiftRepository;
use App\Giving\Domain\Repository\GivingHistoryRepository;
use App\Giving\Domain\Repository\ReconciliationRepository;
use App\Shared\Application\CommandHandler;
use Psr\Clock\ClockInterface;

/**
 * Appends versioned review evidence to a batch. Locks the batch's gifts sorted by id, then the batch, so a concurrent
 * reversal cannot change the reversed amount the review records. Idempotent on the request id.
 */
final readonly class ReviewReconciliationHandler implements CommandHandler
{
    public function __construct(
        private GivingAccess $access,
        private GiftRepository $gifts,
        private ReconciliationRepository $batches,
        private GivingHistoryRepository $history,
        private ClockInterface $clock,
    ) {
    }

    /** @return string the review id, which is the request id */
    public function handle(ReviewReconciliationCommand $command): string
    {
        $this->access->reconcile($command->actorId);
        $note = GivingRules::text($command->note, 1000, 'Review evidence');
        $hash = GivingHash::of('batch-review', $command->actorId, $command->batchId, $command->expectedVersion, $command->outcome, $note);

        $batch = $this->batches->find($command->batchId) ?? throw GivingException::missing();
        $gifts = $batch->giftIds;
        sort($gifts, \SORT_STRING);
        foreach ($gifts as $gift) {
            $this->gifts->findForUpdate($gift) ?? throw GivingException::missing();
        }
        $this->batches->lock($batch->id);
        $replay = $this->batches->reviewedBy($command->requestId, $hash);
        if (null !== $replay) {
            return $replay;
        }
        $version = $this->batches->version($batch->id);
        GivingRules::version($version, $command->expectedVersion);
        $now = $this->clock->now();
        $review = $batch->review($command->requestId, $version, $command->outcome, $note, $this->batches->reversedMinor($batch->id), $command->actorId, $now);
        $this->batches->insertReview($review, $hash);
        $this->history->append($batch->id, GivingHistoryRepository::BATCH, 'BATCH_REVIEWED', $note, $command->actorId, $now);

        return $review->id;
    }
}

<?php

declare(strict_types=1);

namespace App\Giving\Application\Command\CreateReconciliation;

use App\Giving\Application\GivingAccess;
use App\Giving\Application\GivingHash;
use App\Giving\Domain\Gift;
use App\Giving\Domain\GivingException;
use App\Giving\Domain\GivingRules;
use App\Giving\Domain\Reconciliation;
use App\Giving\Domain\Repository\GiftRepository;
use App\Giving\Domain\Repository\GivingHistoryRepository;
use App\Giving\Domain\Repository\ReconciliationRepository;
use App\Shared\Application\ChurchCalendar;
use App\Shared\Application\CommandHandler;
use App\Shared\Domain\JavaString;
use Psr\Clock\ClockInterface;

/**
 * Freezes a bank or cash batch of gifts. Idempotent on the request id. The gifts are locked sorted by id, so two batches
 * claiming one gift serialise and the second sees it reconciled.
 */
final readonly class CreateReconciliationHandler implements CommandHandler
{
    public function __construct(
        private GivingAccess $access,
        private GiftRepository $gifts,
        private ReconciliationRepository $batches,
        private GivingHistoryRepository $history,
        private ChurchCalendar $calendar,
        private ClockInterface $clock,
    ) {
    }

    /** @return string the batch id, which is the request id */
    public function handle(CreateReconciliationCommand $command): string
    {
        $this->access->reconcile($command->actorId);
        $reconciledOn = GivingRules::date($command->reconciledOn, $this->calendar->today());
        $reference = GivingRules::text($command->reference, 200, 'Batch reference');
        $ids = self::distinctGifts($command->giftIds);
        $reason = null === $command->reason ? '' : JavaString::trim($command->reason);
        $hash = GivingHash::of('batch', $command->actorId, $reference, $command->expectedMinor, $command->currency, $reconciledOn, $reason, $ids);

        $replay = $this->batches->createdBy($command->requestId, $hash);
        if (null !== $replay) {
            return $replay;
        }
        $gifts = array_map(fn (string $id): Gift => $this->gifts->findForUpdate($id) ?? throw GivingException::missing(), $ids);
        $retry = $this->batches->createdBy($command->requestId, $hash);
        if (null !== $retry) {
            return $retry;
        }
        $actual = 0;
        foreach ($gifts as $gift) {
            if ($gift->money->currency !== $command->currency || $gift->receivedOn->isAfter($reconciledOn)
                || $this->gifts->hasReversal($gift->id) || $this->batches->reconciled($gift->id)) {
                throw GivingException::conflict('Every selected gift must be unreconciled, unreversed, in the batch currency and received by the reconciliation date.');
            }
            $actual += $gift->money->amountMinor;
        }
        $now = $this->clock->now();
        $batch = Reconciliation::create($command->requestId, $reference, $command->expectedMinor, $actual, $command->currency, $reconciledOn, $reason, $command->actorId, $now, $ids);
        $this->batches->insert($batch, $hash);
        $this->history->append($batch->id, GivingHistoryRepository::BATCH, 'BATCH_CREATED', $batch->reason, $command->actorId, $now);

        return $batch->id;
    }

    /**
     * @param list<string|null>|null $giftIds
     *
     * @return list<string> sorted by id, the order the gifts are locked and hashed in
     */
    private static function distinctGifts(?array $giftIds): array
    {
        if (null === $giftIds || [] === $giftIds || \count($giftIds) > Reconciliation::MAX_GIFTS || \in_array(null, $giftIds, true)
            || \count(array_unique($giftIds)) !== \count($giftIds)) {
            throw GivingException::invalid('Select 1–100 distinct gifts.');
        }
        $ids = array_values(array_filter($giftIds, static fn (?string $id): bool => null !== $id));
        usort($ids, strcmp(...));

        return $ids;
    }
}

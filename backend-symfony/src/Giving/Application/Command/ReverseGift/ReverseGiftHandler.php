<?php

declare(strict_types=1);

namespace App\Giving\Application\Command\ReverseGift;

use App\Giving\Application\GivingAccess;
use App\Giving\Application\GivingHash;
use App\Giving\Domain\GivingException;
use App\Giving\Domain\GivingRules;
use App\Giving\Domain\Repository\GiftRepository;
use App\Giving\Domain\Repository\GivingHistoryRepository;
use App\Shared\Application\ChurchCalendar;
use App\Shared\Application\CommandHandler;
use Psr\Clock\ClockInterface;

/**
 * Records the one full reversal a manual gift may have. Idempotent on the request id. The gift row lock serialises
 * concurrent reversals, so the second one sees the first and is refused. Online gifts are corrected by Payments refunds.
 */
final readonly class ReverseGiftHandler implements CommandHandler
{
    public function __construct(
        private GivingAccess $access,
        private GiftRepository $gifts,
        private GivingHistoryRepository $history,
        private ChurchCalendar $calendar,
        private ClockInterface $clock,
    ) {
    }

    /** @return string the reversal id, which is the request id */
    public function handle(ReverseGiftCommand $command): string
    {
        $this->access->reverse($command->actorId);
        GivingRules::date($command->reversedOn, $this->calendar->today());
        $reason = GivingRules::text($command->reason, 1000, 'Reversal reason');
        $hash = GivingHash::of('reverse', $command->actorId, $command->giftId, $command->reversedOn, $reason);

        $gift = $this->gifts->findForUpdate($command->giftId) ?? throw GivingException::missing();
        $replay = $this->gifts->reversedBy($command->requestId, $hash);
        if (null !== $replay) {
            return $replay;
        }
        if ('ONLINE' === $gift->method) {
            throw GivingException::conflict('Use Online payments to record a provider-completed refund.');
        }
        if ($this->gifts->hasReversal($gift->id)) {
            throw GivingException::conflict('This gift already has a full reversal.');
        }
        $now = $this->clock->now();
        $reversal = $gift->reverse($command->requestId, $command->reversedOn, $reason, $command->actorId, $now);
        $this->gifts->insertReversal($reversal, $hash);
        $this->history->append($gift->id, GivingHistoryRepository::GIFT, 'GIFT_REVERSED', $reason, $command->actorId, $now);

        return $reversal->id;
    }
}

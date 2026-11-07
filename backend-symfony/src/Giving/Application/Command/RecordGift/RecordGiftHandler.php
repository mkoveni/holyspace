<?php

declare(strict_types=1);

namespace App\Giving\Application\Command\RecordGift;

use App\Giving\Application\GivingAccess;
use App\Giving\Application\GivingHash;
use App\Giving\Domain\Gift;
use App\Giving\Domain\GivingException;
use App\Giving\Domain\GivingRules;
use App\Giving\Domain\Money;
use App\Giving\Domain\Repository\FundRepository;
use App\Giving\Domain\Repository\GiftRepository;
use App\Giving\Domain\Repository\GivingHistoryRepository;
use App\People\Application\Contract\PersonDirectory;
use App\Shared\Application\ChurchCalendar;
use App\Shared\Application\CommandHandler;
use Psr\Clock\ClockInterface;

/**
 * Posts a manual (cash, bank or other) gift. Idempotent on the request id: an identical retry returns the same gift,
 * different details are a conflict. Lock order: donor person row, then the allocated funds sorted by id.
 */
final readonly class RecordGiftHandler implements CommandHandler
{
    public function __construct(
        private GivingAccess $access,
        private GiftRepository $gifts,
        private FundRepository $funds,
        private GivingHistoryRepository $history,
        private PersonDirectory $people,
        private ChurchCalendar $calendar,
        private ClockInterface $clock,
    ) {
    }

    /** @return string the gift id, which is the request id */
    public function handle(RecordGiftCommand $command): string
    {
        $this->access->manage($command->actorId);
        if (!\in_array($command->method ?? '', Gift::MANUAL_METHODS, true)) {
            throw GivingException::invalid('Online gifts require verified Payments confirmation.');
        }
        $now = $this->clock->now();
        $gift = Gift::record(
            $command->requestId,
            $command->donorId,
            Money::of($command->amountMinor, (string) $command->currency),
            $command->receivedOn,
            $command->method,
            $command->externalReference,
            $command->allocations,
            $command->actorId,
            $now,
        );
        GivingRules::date($gift->receivedOn, $this->calendar->today());
        $allocations = $gift->allocationsByFund();
        $hash = GivingHash::of('gift', $command->actorId, $gift->donorId, $gift->money, $gift->receivedOn, $gift->method, $gift->externalReference, $allocations);

        $replay = $this->gifts->postedBy($gift->id, $hash);
        if (null !== $replay) {
            return $replay;
        }
        if (null !== $gift->donorId && true !== $this->people->lock($gift->donorId)?->available) {
            throw GivingException::invalid('Choose an available person or record an anonymous gift.');
        }
        foreach ($allocations as $allocation) {
            if (true !== $this->funds->findForUpdate($allocation->fundId)?->active) {
                throw GivingException::conflict('An allocated fund is missing or inactive.');
            }
        }
        // A concurrent identical request may have committed while this one waited for the locks.
        $replay = $this->gifts->postedBy($gift->id, $hash);
        if (null !== $replay) {
            return $replay;
        }
        $this->gifts->insert($gift, $hash);
        $this->history->append($gift->id, GivingHistoryRepository::GIFT, 'GIFT_POSTED', '', $command->actorId, $now);

        return $gift->id;
    }
}

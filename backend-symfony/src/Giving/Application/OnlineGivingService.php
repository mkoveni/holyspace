<?php

declare(strict_types=1);

namespace App\Giving\Application;

use App\Giving\Application\Contract\OnlineDonor;
use App\Giving\Application\Contract\OnlineGivingLedger;
use App\Giving\Domain\Gift;
use App\Giving\Domain\GivingException;
use App\Giving\Domain\GivingRules;
use App\Giving\Domain\PledgeAssignment;
use App\Giving\Domain\Repository\FundRepository;
use App\Giving\Domain\Repository\GiftRepository;
use App\Giving\Domain\Repository\GivingHistoryRepository;
use App\Giving\Domain\Repository\OnlineGivingRepository;
use App\Giving\Domain\Repository\PledgeRepository;
use App\People\Application\Contract\PersonDirectory;
use App\Shared\Application\ChurchCalendar;
use App\Shared\Application\UnitOfWork;
use App\Shared\Domain\CalendarDate;
use Psr\Clock\ClockInterface;

/**
 * Trusted capability invoked only by Payments after durable provider verification; no user payment assertions. It is not
 * a command handler: Payments calls it from its own handlers, so each call joins the caller's transaction (UnitOfWork
 * nests) and opens one only when called outside a transaction, as Java's `tx.execute` does.
 *
 * Lock order: validate and post take the donor person row, then the fund; refund takes the gift row, then its pledges
 * sorted by id (the order every assignment write and the assignment guard trigger use).
 */
final readonly class OnlineGivingService implements OnlineGivingLedger
{
    public const string REFUND_REVIEW = 'Source gift refunded; review the remaining credit.';

    public function __construct(
        private GiftRepository $gifts,
        private FundRepository $funds,
        private OnlineGivingRepository $online,
        private PledgeRepository $pledges,
        private GivingHistoryRepository $history,
        private PersonDirectory $people,
        private UnitOfWork $transaction,
        private ChurchCalendar $calendar,
        private ClockInterface $clock,
    ) {
    }

    public function validate(string $donorId, string $fundId): OnlineDonor
    {
        return $this->transaction->execute(function () use ($donorId, $fundId): OnlineDonor {
            $person = $this->people->lock($donorId);
            if (null === $person || !$person->available) {
                throw GivingException::invalid('Choose an available donor.');
            }
            if (true !== $this->funds->findForUpdate($fundId)?->active) {
                throw GivingException::conflict('Choose an active giving fund.');
            }

            return new OnlineDonor($person->name);
        });
    }

    public function validateFund(string $fundId): void
    {
        $this->transaction->execute(function () use ($fundId): void {
            if (true !== $this->funds->findForUpdate($fundId)?->active) {
                throw GivingException::conflict('Choose an active giving fund.');
            }
        });
    }

    public function post(
        string $chargeId,
        ?string $donorId,
        string $fundId,
        int $amountMinor,
        string $reference,
        CalendarDate $receivedOn,
        string $initiatedBy,
    ): string {
        GivingRules::date($receivedOn, $this->calendar->today());
        $hash = GivingHash::of('verified-payment', $chargeId, $donorId, $fundId, $amountMinor, $reference, $receivedOn, $initiatedBy);

        return $this->transaction->execute(function () use ($chargeId, $donorId, $fundId, $amountMinor, $reference, $receivedOn, $initiatedBy, $hash): string {
            $replay = $this->gifts->postedBy($chargeId, $hash);
            if (null !== $replay) {
                return $replay;
            }
            if (null !== $donorId) {
                $this->people->lock($donorId) ?? throw GivingException::missing();
            }
            $this->funds->findForUpdate($fundId) ?? throw GivingException::missing();
            // A concurrent identical post may have committed while this one waited for the locks.
            $replay = $this->gifts->postedBy($chargeId, $hash);
            if (null !== $replay) {
                return $replay;
            }
            $now = $this->clock->now();
            $gift = Gift::online($chargeId, $donorId, $fundId, $amountMinor, $reference, $receivedOn, $initiatedBy, $now);
            $this->gifts->insert($gift, $hash);
            $this->online->source($gift->id, $chargeId, $reference);
            $this->history->append($gift->id, GivingHistoryRepository::GIFT, 'PAYMENT_POSTED', $reference, $initiatedBy, $now);

            return $gift->id;
        });
    }

    public function refund(
        string $refundId,
        string $giftId,
        int $amountMinor,
        string $reference,
        CalendarDate $refundedOn,
        string $initiatedBy,
    ): void {
        $this->transaction->execute(function () use ($refundId, $giftId, $amountMinor, $reference, $refundedOn, $initiatedBy): void {
            $gift = $this->gifts->findForUpdate($giftId) ?? throw GivingException::missing();
            if ($this->online->refundExists($refundId, $giftId, $amountMinor, $reference, $refundedOn)) {
                return;
            }
            GivingRules::date($refundedOn, $this->calendar->today());
            $now = $this->clock->now();
            $refund = $gift->refundOnline($refundId, $amountMinor, $reference, $refundedOn, $this->gifts->refunded($giftId), $initiatedBy, $now);

            $assignments = $this->online->activeAssignments($giftId);
            $pledgeIds = array_values(array_unique(array_map(static fn (PledgeAssignment $a): string => $a->pledgeId, $assignments)));
            sort($pledgeIds, \SORT_STRING);
            foreach ($pledgeIds as $pledgeId) {
                $pledge = $this->pledges->findForUpdate($pledgeId) ?? throw GivingException::missing();
                foreach ($assignments as $assignment) {
                    if ($assignment->pledgeId === $pledgeId) {
                        $this->pledges->saveAssignment($assignment->cancel($assignment->version, self::REFUND_REVIEW, $initiatedBy, $now));
                    }
                }
                $this->pledges->save($pledge->revised());
                $this->history->append($pledgeId, GivingHistoryRepository::PLEDGE, 'PAYMENT_REFUND', $reference, $initiatedBy, $now);
            }
            $this->online->refund($refund);
            $this->history->append($giftId, GivingHistoryRepository::GIFT, 'PAYMENT_REFUNDED', $reference, $initiatedBy, $now);
        });
    }
}

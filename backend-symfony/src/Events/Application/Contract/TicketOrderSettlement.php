<?php

declare(strict_types=1);

namespace App\Events\Application\Contract;

/**
 * Events' side of a verified ticket payment, called by Payments inside its posting transaction after the payments order
 * row is locked. Every method is idempotent. Outcomes are plain words so Payments never learns Events' internals.
 */
interface TicketOrderSettlement
{
    public const string ISSUED = 'ISSUED';
    public const string REVIEW = 'REVIEW';
    public const string DONE = 'DONE';

    /** A verified live payment of `$amountMinor`: ISSUED when tickets exist, REVIEW when the money must be refunded or checked by staff. */
    public function paid(string $ticketOrderId, int $amountMinor): string;

    /**
     * A verified refund of `$amountMinor` was applied. `$full` is true when the order's whole payment is now refunded. DONE,
     * or REVIEW when staff must choose which tickets a partial refund covers.
     */
    public function refunded(string $ticketOrderId, bool $full, int $amountMinor): string;

    /**
     * Queues the tickets email of a paid order once, in its own transaction, after the payment has committed. A failure to
     * queue never undoes the verified payment: the next posting attempt retries it. Safe to call repeatedly.
     */
    public function deliver(string $ticketOrderId): void;

    /** A sandbox (test mode) payment never settles money: the hold is released and nothing is issued. */
    public function released(string $ticketOrderId): void;
}

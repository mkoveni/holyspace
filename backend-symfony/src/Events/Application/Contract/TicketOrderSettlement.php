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

    /** A verified refund was applied. `$full` is true when the order's whole payment is now refunded. DONE, or REVIEW when staff must choose tickets. */
    public function refunded(string $ticketOrderId, bool $full): string;

    /** A sandbox (test mode) payment never settles money: the hold is released and nothing is issued. */
    public function released(string $ticketOrderId): void;
}

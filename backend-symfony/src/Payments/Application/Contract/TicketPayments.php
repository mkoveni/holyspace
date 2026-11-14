<?php

declare(strict_types=1);

namespace App\Payments\Application\Contract;

/**
 * What Events needs from Payments to sell public tickets. Payments owns the order, the hosted checkout and the verified
 * evidence; Events owns what was bought. The payments order shares the id of its ticket order.
 */
interface TicketPayments
{
    /**
     * Records the payment intent for a ticket order, in the caller's transaction: the provider mode and the immutable
     * configuration revision are pinned here. The email arrives encrypted with the payments vault. Idempotent on the id.
     */
    public function open(string $ticketOrderId, int $amountMinor, ?string $buyerName, string $encryptedEmail, string $emailHash, string $accessHash, string $requestHash): void;

    /** Creates (once) or returns the hosted checkout. Calls the provider, so it must never run inside a transaction. */
    public function checkout(string $ticketOrderId): HostedCheckout;
}

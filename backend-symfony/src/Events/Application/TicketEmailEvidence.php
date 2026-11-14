<?php

declare(strict_types=1);

namespace App\Events\Application;

use App\Events\Application\Contract\TicketCatalog;
use App\Events\Domain\Repository\TicketRepository;
use App\Payments\Application\Contract\PayloadVault;

/**
 * Judges a frozen tickets email before each send. Kept apart from TicketEmails because Communications collects the
 * delivery eligibilities: the judge must not depend on the delivery queue it is called from.
 */
final readonly class TicketEmailEvidence
{
    public function __construct(private TicketRepository $tickets, private TicketCatalog $catalog, private PayloadVault $vault)
    {
    }

    /** Whether the frozen delivery is still true: the order is still paid, nothing about it changed, the address and content match. */
    public function stillValid(string $communicationId, string $deliveryId, string $reviewHash, string $recipient, string $subject, string $html, int $version): bool
    {
        $email = $this->tickets->emailById($communicationId);
        if (null === $email || $email['deliveryId'] !== $deliveryId || $email['reviewHash'] !== $reviewHash) {
            return false;
        }
        $order = $this->tickets->find($email['orderId']);
        if (null === $order || !TicketEmails::deliverable($order) || $this->vault->decrypt($order->buyerEmailEncrypted) !== $recipient) {
            return false;
        }
        $event = $this->catalog->event($order->occurrenceId);

        return null !== $event && TicketEmails::hash($order->id, $recipient, $subject, $html, $version) === $reviewHash;
    }
}

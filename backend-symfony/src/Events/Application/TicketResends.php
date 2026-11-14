<?php

declare(strict_types=1);

namespace App\Events\Application;

use App\Events\Application\Contract\EventJournal;
use App\Events\Domain\EventException;
use App\Events\Domain\Repository\TicketRepository;
use App\IdentityAccess\Application\Contract\PermissionChecker;
use App\Payments\Application\Contract\GuestAccess;
use DateInterval;
use Psr\Clock\ClockInterface;

/**
 * Sends a paid order's tickets email again (design 5.7). Staff name an order; a buyer names an email address and is
 * always told the same thing, so the form cannot be used to find out who bought tickets. Each order is resent at most
 * once every ten minutes.
 */
final readonly class TicketResends
{
    private const string COOLDOWN = 'PT10M';

    public function __construct(
        private TicketRepository $tickets,
        private TicketEmails $emails,
        private GuestAccess $access,
        private PermissionChecker $permissions,
        private EventJournal $journal,
        private ClockInterface $clock,
    ) {
    }

    public function byStaff(string $actorId, string $occurrenceId, string $orderId): void
    {
        $this->permissions->requireActorPermission($actorId, 'events.tickets.manage');
        $order = $this->tickets->find($orderId);
        if (null === $order || $order->occurrenceId !== $occurrenceId) {
            throw EventException::missing('Ticket order');
        }
        if (!TicketEmails::deliverable($order)) {
            throw EventException::conflict('Only the tickets of a paid order can be sent again.');
        }
        if (!$this->due($orderId)) {
            throw EventException::throttled('The tickets were sent a moment ago. Wait a few minutes before sending them again.');
        }
        if (!$this->emails->queueFor($orderId, true)) {
            throw EventException::conflict('The tickets email could not be queued. Please try again.');
        }
        $this->journal->audit($actorId, 'tickets.email.resent', $orderId, 'Tickets email sent again by staff.', $this->clock->now());
    }

    /** Never says whether the address has tickets. Invalid addresses and unknown buyers are silently ignored. */
    public function byBuyer(string $email): void
    {
        try {
            $normalized = TicketOrderStarter::email($email);
        } catch (EventException) {
            return;
        }
        foreach ($this->tickets->currentOrdersOf($this->access->emailHash($normalized), $this->clock->now(), 3) as $orderId) {
            if ($this->due($orderId)) {
                $this->emails->queueFor($orderId, true);
            }
        }
    }

    private function due(string $orderId): bool
    {
        $last = $this->tickets->email($orderId);

        return null === $last || $last['createdAt'] <= $this->clock->now()->sub(new DateInterval(self::COOLDOWN));
    }
}

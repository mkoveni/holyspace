<?php

declare(strict_types=1);

namespace App\Events\Application;

use App\Communications\Application\Contract\DeliverySnapshot;
use App\Communications\Application\Contract\EmailDeliveries;
use App\Communications\Application\Contract\EmailTemplates;
use App\Events\Application\Contract\TicketCatalog;
use App\Events\Domain\Repository\TicketRepository;
use App\Events\Domain\TicketOrder;
use App\Payments\Application\Contract\GuestAccess;
use App\Payments\Application\Contract\PayloadVault;
use App\Shared\Application\IdGenerator;
use App\Shared\Application\UnitOfWork;
use DateTimeImmutable;
use DateTimeZone;
use Psr\Clock\ClockInterface;
use RuntimeException;
use Throwable;

/**
 * The email that carries a paid order's tickets: one per order, frozen to what was rendered and judged again by Events'
 * delivery eligibility before every send. The link opens the wallet; its token travels in the URL fragment so it never
 * reaches server logs or a Referer header.
 */
final readonly class TicketEmails
{
    public const string TEMPLATE = 'events.tickets';
    /** Recorded as the approver of a system-queued public message: there is no signed-in account (same id Payments uses for guests). */
    public const string SYSTEM_ACTOR = '00000000-0000-0000-0000-0000000000a1';

    public function __construct(
        private TicketRepository $tickets,
        private TicketCatalog $catalog,
        private EmailTemplates $templates,
        private EmailDeliveries $deliveries,
        private PayloadVault $vault,
        private GuestAccess $access,
        private UnitOfWork $transactions,
        private ClockInterface $clock,
        private IdGenerator $ids,
        private string $publicUrl,
    ) {
    }

    /**
     * Queues the email unless it exists already; `$again` queues a fresh one (a resend). Never throws: the payment it
     * follows is already final. Returns whether an email was queued.
     */
    public function queueFor(string $orderId, bool $again = false): bool
    {
        try {
            return $this->transactions->execute(function () use ($orderId, $again): bool {
                $found = $this->tickets->find($orderId);
                if (null === $found) {
                    return false;
                }
                $this->tickets->lockOccurrence($found->occurrenceId);
                $order = $this->tickets->lock($orderId);
                if (null === $order || !self::deliverable($order) || (!$again && null !== $this->tickets->email($orderId))) {
                    return false;
                }
                $recipient = $this->vault->decrypt($order->buyerEmailEncrypted);
                $rendered = $this->templates->render(self::TEMPLATE, $this->values($order));
                $id = $this->ids->uuid();
                $delivery = $this->ids->uuid();
                $now = $this->clock->now();
                $hash = self::hash($order->id, $recipient, $rendered->subject, $rendered->html, $rendered->version);
                $this->tickets->recordEmail($id, $order->id, $delivery, $hash, $now);
                $this->deliveries->queue('events', new DeliverySnapshot(
                    $delivery,
                    $id,
                    null,
                    null,
                    $recipient,
                    $rendered->subject,
                    $rendered->html,
                    $hash,
                    self::SYSTEM_ACTOR,
                    $now,
                    $now,
                    'QUEUED',
                    0,
                    '<'.$delivery.'@onechurch.delivery>',
                    null,
                    null,
                    null,
                    '',
                    false,
                    0,
                    'text/html',
                    self::TEMPLATE,
                    $rendered->version,
                ));
                $this->tickets->audit($order->id, 'TICKET_EMAIL_QUEUED', $again ? 'Tickets email queued again (resend).' : 'Tickets email queued for the buyer.', $now);

                return true;
            });
        } catch (Throwable) {
            // Retried by the next posting attempt or a resend; the verified payment is never undone by an email problem.
            return false;
        }
    }

    public static function deliverable(TicketOrder $order): bool
    {
        return \in_array($order->status, ['PAID', 'PARTIALLY_REFUNDED'], true);
    }

    /** @return array<string, string> */
    private function values(TicketOrder $order): array
    {
        $event = $this->catalog->event($order->occurrenceId) ?? throw new RuntimeException('Event missing.');
        $zone = new DateTimeZone($event['timeZone']);

        return [
            'name' => $order->buyerName,
            'event' => $event['title'],
            'startsAt' => self::local($event['startsAt'], $zone),
            'timeZone' => $event['timeZone'],
            'venue' => $event['venueName'],
            'seats' => (string) $order->seats(),
            'total' => 0 === $order->totalMinor ? 'Free' : 'ZAR '.number_format($order->totalMinor / 100, 2, '.', ' '),
            'reference' => strtoupper(substr($order->id, 0, 8)),
            'link' => rtrim($this->publicUrl, '/').'/tickets#'.$this->access->token('wallet-'.$order->id),
        ];
    }

    private static function local(DateTimeImmutable $at, DateTimeZone $zone): string
    {
        return $at->setTimezone($zone)->format('D j M Y, H:i');
    }

    public static function hash(string $orderId, string $recipient, string $subject, string $html, int $version): string
    {
        return hash('sha256', json_encode(['ticket-email', $orderId, $recipient, $subject, $html, $version], \JSON_THROW_ON_ERROR));
    }
}

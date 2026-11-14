<?php

declare(strict_types=1);

namespace App\Payments\Application;

use App\Communications\Application\Contract\DeliverySnapshot;
use App\Communications\Application\Contract\EmailDeliveries;
use App\Communications\Application\Contract\EmailTemplates;
use App\Events\Application\Contract\TicketOrderSettlement;
use App\Payments\Application\Contract\PayloadVault;
use App\Payments\Application\Contract\PaymentReader;
use App\Payments\Domain\GuestConfirmation;
use App\Payments\Domain\PaymentOrder;
use App\Payments\Domain\Repository\GuestConfirmationRepository;
use App\Payments\Domain\Repository\PaymentRepository;
use App\Shared\Application\IdGenerator;
use App\Shared\Application\UnitOfWork;
use Psr\Clock\ClockInterface;

/**
 * The automatic thank-you email of a verified public payment. It is the same published template as staff-approved
 * donor confirmations (and says it is not a tax certificate), is queued once per posted, unrefunded live charge, and is
 * frozen to what was rendered; Payments' delivery eligibility re-judges it from the stored evidence before each send.
 * Safe to call repeatedly: a charge that already has its confirmation is skipped.
 */
final readonly class GuestConfirmations
{
    public function __construct(
        private PaymentRepository $payments,
        private PaymentReader $reader,
        private GuestConfirmationRepository $confirmations,
        private PayloadVault $vault,
        private EmailTemplates $templates,
        private EmailDeliveries $deliveries,
        private UnitOfWork $transactions,
        private ClockInterface $clock,
        private IdGenerator $ids,
        private TicketOrderSettlement $tickets,
    ) {
    }

    public function queueFor(string $orderId): void
    {
        $ticket = $this->payments->find($orderId);
        if (null !== $ticket && PaymentOrder::TICKET === $ticket->purpose) {
            // A ticket buyer's email carries their tickets; Events judges, renders and queues it after the payment committed.
            $this->tickets->deliver((string) $ticket->purposeRef);

            return;
        }
        $this->transactions->execute(function () use ($orderId): void {
            $order = $this->payments->lock($orderId);
            if (null === $order || PaymentOrder::GIVING !== $order->purpose || !$order->isGuest() || !$order->isLive() || null === $order->guestEmailEncrypted) {
                return;
            }
            $detail = $this->reader->detail($orderId);
            if (null === $detail) {
                return;
            }
            $recipient = $this->vault->decrypt($order->guestEmailEncrypted);
            foreach ($detail->charges as $view) {
                $charge = $view->charge;
                if (null === $charge->giftId || 0 !== $view->refundedMinor || $detail->refundTouches($charge->id)
                    || null !== $this->confirmations->forCharge($charge->id)) {
                    continue;
                }
                $rendered = $this->templates->render(PaymentReceiptPreview::TEMPLATE, [
                    'name' => $order->guestName ?? 'Friend',
                    'amount' => 'ZAR '.PaymentReceiptPreview::decimal($charge->amountMinor),
                    'date' => $charge->receivedOn->iso(),
                    'provider' => $order->provider,
                    'reference' => $charge->reference,
                ]);
                $id = $this->ids->uuid();
                $delivery = $this->ids->uuid();
                $now = $this->clock->now();
                $hash = self::hash($order->id, $charge->id, $charge->giftId, $charge->amountMinor, $recipient, $rendered->subject, $rendered->html, $rendered->version);
                $this->confirmations->insert(new GuestConfirmation($id, $order->id, $charge->id, $charge->giftId, $delivery, $hash, $now));
                $this->deliveries->queue('payments', new DeliverySnapshot(
                    $delivery,
                    $id,
                    null,
                    null,
                    $recipient,
                    $rendered->subject,
                    $rendered->html,
                    $hash,
                    PaymentOrder::GUEST_ACTOR,
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
                    PaymentReceiptPreview::TEMPLATE,
                    $rendered->version
                ));
                $this->payments->audit($order->id, 'GUEST_CONFIRMATION_QUEUED', 'Confirmation email queued for the public giver.', null, $now);
            }
        });
    }

    public static function hash(string $orderId, string $chargeId, string $giftId, int $amountMinor, string $recipient, string $subject, string $html, int $version): string
    {
        return PaymentHash::of('guest-confirmation', $orderId, $chargeId, $giftId, $amountMinor, $recipient, $subject, $html, $version);
    }
}

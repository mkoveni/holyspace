<?php

declare(strict_types=1);

namespace App\Payments\Infrastructure;

use App\Communications\Application\Contract\DeliveryEligibility;
use App\Communications\Application\Contract\DeliverySnapshot;
use App\IdentityAccess\Application\Contract\PermissionChecker;
use App\IdentityAccess\Application\Contract\ResponsibleAccountDirectory;
use App\Payments\Application\Contract\PayloadVault;
use App\Payments\Application\Contract\PaymentReader;
use App\Payments\Application\Contract\PaymentReceiptReader;
use App\Payments\Application\GuestConfirmations;
use App\Payments\Application\PaymentReceiptPreview;
use App\Payments\Domain\PaymentException;
use App\Payments\Domain\PaymentOrder;
use App\Payments\Domain\Repository\GuestConfirmationRepository;
use App\Payments\Domain\Repository\PaymentRepository;
use App\People\Application\Contract\PersonDirectory;
use App\People\Application\Contract\PersonEmailDirectory;
use DateTimeImmutable;

/**
 * Revalidates a donor confirmation before each send: the approver still holds both permissions, the receipt matches
 * the frozen delivery, the live charge is still posted and unrefunded, the address is still the donor's, and the
 * re-rendered hash of the frozen content still matches.
 */
final readonly class PaymentDeliveryEligibility implements DeliveryEligibility
{
    public function __construct(
        private PaymentReceiptReader $receipts,
        private PaymentReader $payments,
        private PermissionChecker $auth,
        private ResponsibleAccountDirectory $accounts,
        private PersonDirectory $people,
        private PersonEmailDirectory $emails,
        private GuestConfirmationRepository $guestConfirmations,
        private PayloadVault $vault,
        private PaymentRepository $orders,
    ) {
    }

    public function sourceContext(): string
    {
        return 'payments';
    }

    public function authorize(DeliverySnapshot $delivery, string $actor, string $operation): void
    {
        if ('queue' === $operation && PaymentOrder::GUEST_ACTOR === $delivery->approvedBy && null !== $this->guestConfirmations->find($delivery->communicationId)) {
            // A public giver's thank-you is queued by the system after the gift posts: no signed-in staff member exists. The
            // frozen evidence is judged again before every send (eligible()); staff still need both permissions to retry or cancel.
            return;
        }
        $this->auth->requireActorPermission($actor, 'payments.manage');
        $this->auth->requireActorPermission($actor, 'payments.receipts.send');
    }

    public function eligible(DeliverySnapshot $d, DateTimeImmutable $at): bool
    {
        if (PaymentOrder::GUEST_ACTOR === $d->approvedBy) {
            return $this->guestEligible($d);
        }
        if (!$this->accounts->eligible($d->approvedBy, 'payments.manage') || !$this->accounts->eligible($d->approvedBy, 'payments.receipts.send')
            || null === $d->templateVersion || PaymentReceiptPreview::TEMPLATE !== $d->templateKey) {
            return false;
        }
        $receipt = $this->receipts->receipt($d->communicationId);
        if (null === $receipt || $receipt->deliveryId !== $d->id || $receipt->reviewHash !== $d->reviewHash || $receipt->contactId !== $d->contactId
            || true !== $this->people->get($receipt->donorId)?->available) {
            return false;
        }
        try {
            $detail = $this->payments->detail($receipt->orderId);
            if (null === $detail || 'live' !== $detail->order->mode || $detail->refundTouches($receipt->chargeId)) {
                return false;
            }
            $charge = $detail->charge($receipt->chargeId);
        } catch (PaymentException) {
            return false;
        }
        $current = false;
        foreach ($this->emails->active($receipt->donorId) as $email) {
            $current = $current || ($email->id === $d->contactId && $email->value === $d->recipient);
        }

        return $receipt->giftId === $charge->charge->giftId && 0 === $charge->refundedMinor && $current
            && PaymentReceiptPreview::hash($detail, $receipt->chargeId, (string) $d->contactId, $d->recipient, $d->subject, $d->body, $d->templateVersion) === $d->reviewHash;
    }

    /** A public giver's confirmation: the evidence row matches, the live charge is still posted and unrefunded, and the address and content are exactly as frozen. */
    private function guestEligible(DeliverySnapshot $d): bool
    {
        $confirmation = $this->guestConfirmations->find($d->communicationId);
        if (null === $confirmation || $confirmation->deliveryId !== $d->id || $confirmation->reviewHash !== $d->reviewHash
            || null === $d->templateVersion || PaymentReceiptPreview::TEMPLATE !== $d->templateKey) {
            return false;
        }
        $order = $this->orders->find($confirmation->orderId);
        if (null === $order || !$order->isGuest() || !$order->isLive() || null === $order->guestEmailEncrypted
            || $this->vault->decrypt($order->guestEmailEncrypted) !== $d->recipient) {
            return false;
        }
        try {
            $detail = $this->payments->detail($order->id);
            if (null === $detail || $detail->refundTouches($confirmation->chargeId)) {
                return false;
            }
            $charge = $detail->charge($confirmation->chargeId);
        } catch (PaymentException) {
            return false;
        }

        return $charge->charge->giftId === $confirmation->giftId && 0 === $charge->refundedMinor
            && GuestConfirmations::hash($order->id, $confirmation->chargeId, $confirmation->giftId, $charge->charge->amountMinor, $d->recipient, $d->subject, $d->body, $d->templateVersion) === $d->reviewHash;
    }
}

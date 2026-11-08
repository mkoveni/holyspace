<?php

declare(strict_types=1);

namespace App\Payments\Application\Command\ApprovePaymentReceipt;

use App\Communications\Application\Contract\DeliverySnapshot;
use App\Communications\Application\Contract\EmailDeliveries;
use App\Payments\Application\Contract\PaymentReceiptReader;
use App\Payments\Application\PaymentAccess;
use App\Payments\Application\PaymentHash;
use App\Payments\Application\PaymentReceiptPreview;
use App\Payments\Domain\PaymentException;
use App\Payments\Domain\PaymentOrder;
use App\Payments\Domain\PaymentReceipt;
use App\Payments\Domain\Repository\PaymentReceiptRepository;
use App\Payments\Domain\Repository\PaymentRepository;
use App\People\Application\Contract\PersonDirectory;
use App\Shared\Application\CommandHandler;
use App\Shared\Application\IdGenerator;
use Psr\Clock\ClockInterface;

/**
 * Approves one frozen donor confirmation of a verified charge and queues it through Communications. The preview is
 * redone under the order and donor locks and must still match the reviewed hash. Idempotent on the request id; one
 * receipt per charge. Lock order: the order, then the donor person.
 */
final readonly class ApprovePaymentReceiptHandler implements CommandHandler
{
    public function __construct(
        private PaymentAccess $access,
        private PaymentReceiptPreview $previews,
        private PaymentReceiptReader $reader,
        private PaymentReceiptRepository $writer,
        private PaymentRepository $payments,
        private PersonDirectory $people,
        private EmailDeliveries $deliveries,
        private ClockInterface $clock,
        private IdGenerator $ids,
    ) {
    }

    public function handle(ApprovePaymentReceiptCommand $c): string
    {
        $this->access->receipts($c->actorId);
        $evidence = PaymentOrder::text($c->evidence, 500, 'Recipient verification evidence');
        $hash = PaymentHash::of($c->actorId, $c->orderId, $c->chargeId, $c->contactId, $c->reviewHash, $evidence);
        $order = $this->payments->lock($c->orderId) ?? throw PaymentException::missing();
        $this->access->receipts($c->actorId);
        $this->people->lock($order->donorId) ?? throw PaymentException::missing();
        $old = $this->reader->receipt($c->requestId);
        if (null !== $old) {
            if ($old->requestHash !== $hash) {
                throw PaymentException::conflict('Receipt request identifier already used.');
            }

            return $old->deliveryId;
        }
        if (null !== $this->reader->forCharge($c->chargeId)) {
            throw PaymentException::conflict('A receipt is already approved for this charge. Review its delivery instead of sending another.');
        }
        $detail = $this->previews->eligible($c->orderId, $c->chargeId);
        $preview = $this->previews->preview($detail, $c->chargeId, $c->contactId);
        if ($preview->hash !== $c->reviewHash) {
            throw PaymentException::conflict('The payment, contact or published template changed. Preview the receipt again.');
        }
        $delivery = $this->ids->uuid();
        $now = $this->clock->now();
        $this->writer->insert(new PaymentReceipt(
            $c->requestId,
            $c->orderId,
            $c->chargeId,
            $order->donorId,
            $c->contactId,
            (string) $detail->charge($c->chargeId)->charge->giftId,
            $delivery,
            $c->actorId,
            $preview->hash,
            $hash,
            $evidence,
            $now
        ));
        $this->deliveries->queue('payments', new DeliverySnapshot(
            $delivery,
            $c->requestId,
            null,
            $c->contactId,
            $preview->recipient,
            $preview->subject,
            $preview->html,
            $preview->hash,
            $c->actorId,
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
            $preview->templateVersion
        ));
        $this->payments->audit($c->orderId, 'RECEIPT_APPROVED', 'Verified donor confirmation queued.', $c->actorId, $now);

        return $delivery;
    }
}

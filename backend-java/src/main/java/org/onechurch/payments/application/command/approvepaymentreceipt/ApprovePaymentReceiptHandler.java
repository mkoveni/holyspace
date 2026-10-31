package org.onechurch.payments.application.command.approvepaymentreceipt;
import org.onechurch.communications.application.contract.DeliverySnapshot;
import org.onechurch.communications.application.contract.EmailDeliveries;
import org.onechurch.payments.application.PaymentAccess;
import org.onechurch.payments.application.PaymentHash;
import org.onechurch.payments.application.PaymentReceiptPreview;
import org.onechurch.payments.application.contract.PaymentReader;
import org.onechurch.payments.application.contract.PaymentReceiptReader;
import org.onechurch.payments.domain.PaymentException;
import org.onechurch.payments.domain.PaymentOrder;
import org.onechurch.payments.domain.PaymentReceipt;
import org.onechurch.payments.domain.repository.PaymentReceiptRepository;
import org.onechurch.payments.domain.repository.PaymentRepository;
import org.onechurch.people.application.contract.PersonDirectory;
import org.onechurch.shared.application.UnitOfWork;

import java.time.Clock;
import java.util.UUID;
public final class ApprovePaymentReceiptHandler {
    private final PaymentAccess access;
    private final PaymentReceiptPreview previews;
    private final PaymentReceiptReader reader;
    private final PaymentReceiptRepository writer;
    private final PaymentRepository payments;
    private final PersonDirectory people;
    private final EmailDeliveries deliveries;
    private final UnitOfWork tx;
    private final Clock clock;
    public ApprovePaymentReceiptHandler(PaymentAccess access, PaymentReceiptPreview previews,
        PaymentReceiptReader reader, PaymentReceiptRepository writer, PaymentRepository payments,
        PersonDirectory people, EmailDeliveries deliveries, UnitOfWork tx, Clock clock) {
        this.access = access;
        this.previews = previews;
        this.reader = reader;
        this.writer = writer;
        this.payments = payments;
        this.people = people;
        this.deliveries = deliveries;
        this.tx = tx;
        this.clock = clock;
    }
    public UUID handle(ApprovePaymentReceiptCommand c) {
        access.receipts(c.actorId());
        String evidence = PaymentOrder.text(c.evidence(), 500, "Recipient verification evidence");
        String hash = PaymentHash.of(c.actorId(), c.orderId(), c.chargeId(), c.contactId(), c.reviewHash(), evidence);
        return tx.execute(() -> {
            PaymentOrder order = payments.lock(c.orderId()).orElseThrow(PaymentException::missing);
            access.receipts(c.actorId());
            people.lock(order.donorId()).orElseThrow(PaymentException::missing);
            PaymentReceipt old = reader.receipt(c.requestId()).orElse(null);
            if (old != null) {
                if (!old.requestHash().equals(hash)) {
                    throw PaymentException.conflict("Receipt request identifier already used.");
                }
                return old.deliveryId();
            }
            if (reader.forCharge(c.chargeId()).isPresent()) {
                throw PaymentException.conflict(
                    "A receipt is already approved for this charge. Review its delivery instead of sending another.");
            }
            PaymentReader.Detail detail = previews.eligible(c.orderId(), c.chargeId());
            PaymentReceiptPreview.Preview preview = previews.preview(detail, c.chargeId(), c.contactId());
            if (!preview.hash().equals(c.reviewHash())) {
                throw PaymentException
                    .conflict("The payment, contact or published template changed. Preview the receipt again.");
            }
            UUID delivery = UUID.randomUUID();
            writer.insert(new PaymentReceipt(c.requestId(), c.orderId(), c.chargeId(), order.donorId(), c.contactId(),
                PaymentReceiptPreview.charge(detail, c.chargeId()).charge().giftId(), delivery, c.actorId(),
                preview.hash(), hash, evidence, clock.instant()));
            deliveries.queue("payments",
                new DeliverySnapshot(delivery, c.requestId(), null, c.contactId(), preview.recipient(),
                    preview.subject(), preview.html(), preview.hash(), c.actorId(), clock.instant(), clock.instant(),
                    "QUEUED", 0, "<" + delivery + "@onechurch.delivery>", null, null, null, "", false, 0, "text/html",
                    "payments.receipt", preview.templateVersion()));
            payments.audit(c.orderId(), "RECEIPT_APPROVED", "Verified donor confirmation queued.", c.actorId(),
                clock.instant());
            return delivery;
        });
    }
}

package org.onechurch.payments.application.query.getpaymentreceipt;
import org.onechurch.communications.application.contract.DeliveryReader;
import org.onechurch.payments.application.PaymentAccess;
import org.onechurch.payments.application.PaymentReceiptPreview;
import org.onechurch.payments.application.contract.PaymentReader;
import org.onechurch.payments.application.contract.PaymentReceiptReader;
import org.onechurch.people.application.contract.PersonEmailDirectory;

import java.util.List;
import java.util.UUID;
public final class GetPaymentReceiptHandler {
    public record Delivery(UUID id, String status, boolean captured, long version, String errorCode) {}
    public record Result(List<PersonEmailDirectory.Email> contacts, PaymentReceiptPreview.Preview preview,
        UUID receiptId, Delivery delivery) {}
    private final PaymentAccess access;
    private final PaymentReceiptPreview previews;
    private final PaymentReceiptReader receipts;
    private final DeliveryReader deliveries;
    public GetPaymentReceiptHandler(PaymentAccess access, PaymentReceiptPreview previews, PaymentReceiptReader receipts,
        DeliveryReader deliveries) {
        this.access = access;
        this.previews = previews;
        this.receipts = receipts;
        this.deliveries = deliveries;
    }
    public Result handle(GetPaymentReceiptQuery q) {
        access.receiptsRead();
        org.onechurch.payments.domain.PaymentReceipt existing = receipts.forCharge(q.chargeId()).orElse(null);
        if (existing != null) {
            if (!existing.orderId().equals(q.orderId())) {
                throw org.onechurch.payments.domain.PaymentException.missing();
            }
            org.onechurch.communications.application.contract.DeliverySnapshot snapshot =
                deliveries.delivery(existing.deliveryId()).orElse(null);
            Delivery delivery = snapshot == null ? null : new Delivery(snapshot.id(), snapshot.status(),
                snapshot.captured(), snapshot.version(), snapshot.errorCode());
            PaymentReceiptPreview.Preview preview =
                snapshot == null ? null : new PaymentReceiptPreview.Preview(snapshot.reviewHash(), snapshot.recipient(),
                    snapshot.subject(), snapshot.body(), snapshot.templateVersion());
            return new Result(List.of(), preview, existing.id(), delivery);
        }
        PaymentReader.Detail detail = previews.eligible(q.orderId(), q.chargeId());
        return new Result(previews.contacts(detail),
            q.contactId() == null ? null : previews.preview(detail, q.chargeId(), q.contactId()), null, null);
    }
}

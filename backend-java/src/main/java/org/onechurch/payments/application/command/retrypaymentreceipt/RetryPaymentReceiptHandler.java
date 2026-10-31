package org.onechurch.payments.application.command.retrypaymentreceipt;
import org.onechurch.communications.application.contract.EmailDeliveries;
import org.onechurch.payments.application.PaymentAccess;
import org.onechurch.payments.application.PaymentReceiptPreview;
import org.onechurch.payments.application.contract.PaymentReceiptReader;
import org.onechurch.payments.domain.PaymentException;
import org.onechurch.payments.domain.PaymentReceipt;
import org.onechurch.payments.domain.repository.PaymentRepository;
import org.onechurch.shared.application.UnitOfWork;
public final class RetryPaymentReceiptHandler {
    private final PaymentAccess access;
    private final PaymentReceiptReader reader;
    private final PaymentReceiptPreview previews;
    private final PaymentRepository payments;
    private final EmailDeliveries deliveries;
    private final UnitOfWork tx;
    public RetryPaymentReceiptHandler(PaymentAccess access, PaymentReceiptReader reader, PaymentReceiptPreview previews,
        PaymentRepository payments, EmailDeliveries deliveries, UnitOfWork tx) {
        this.access = access;
        this.reader = reader;
        this.previews = previews;
        this.payments = payments;
        this.deliveries = deliveries;
        this.tx = tx;
    }
    public void handle(RetryPaymentReceiptCommand c) {
        access.receipts(c.actorId());
        tx.execute(() -> {
            payments.lock(c.orderId()).orElseThrow(PaymentException::missing);
            access.receipts(c.actorId());
            PaymentReceipt receipt = reader.receipt(c.receiptId())
                .filter(v -> v.orderId().equals(c.orderId()))
                .orElseThrow(PaymentException::missing);
            previews.eligible(c.orderId(), receipt.chargeId());
            deliveries.retry(c.actorId(), receipt.deliveryId(), c.expectedVersion(), c.reason());
            return null;
        });
    }
}

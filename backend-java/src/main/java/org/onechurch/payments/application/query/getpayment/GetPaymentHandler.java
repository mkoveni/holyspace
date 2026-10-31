package org.onechurch.payments.application.query.getpayment;
import org.onechurch.payments.application.PaymentAccess;
import org.onechurch.payments.application.contract.PaymentReader;
import org.onechurch.payments.domain.PaymentException;
public final class GetPaymentHandler {
    private final PaymentAccess access;
    private final PaymentReader reader;
    public GetPaymentHandler(PaymentAccess access, PaymentReader reader) {
        this.access = access;
        this.reader = reader;
    }
    public PaymentReader.Detail handle(GetPaymentQuery q) {
        access.read();
        return reader.detail(q.orderId()).orElseThrow(PaymentException::missing);
    }
}

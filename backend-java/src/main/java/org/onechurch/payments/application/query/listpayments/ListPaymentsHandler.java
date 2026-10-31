package org.onechurch.payments.application.query.listpayments;
import org.onechurch.payments.application.PaymentAccess;
import org.onechurch.payments.application.contract.PaymentReader;
import org.onechurch.payments.domain.PaymentException;
public final class ListPaymentsHandler {
    private final PaymentAccess access;
    private final PaymentReader reader;
    public ListPaymentsHandler(PaymentAccess access, PaymentReader reader) {
        this.access = access;
        this.reader = reader;
    }
    public PaymentReader.Page handle(ListPaymentsQuery q) {
        access.read();
        if (q.limit() < 1 || q.limit() > 100 || q.offset() < 0 || q.offset() > 100000) {
            throw PaymentException.invalid("Choose a page of 1–100 payments.");
        }
        return reader.list(q.limit(), q.offset(), q.reviewOnly());
    }
}

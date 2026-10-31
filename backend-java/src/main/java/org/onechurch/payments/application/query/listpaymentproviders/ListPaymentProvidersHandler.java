package org.onechurch.payments.application.query.listpaymentproviders;
import org.onechurch.payments.application.PaymentAccess;
import org.onechurch.payments.application.contract.PaymentGateway;
public final class ListPaymentProvidersHandler {
    private final PaymentAccess access;
    private final PaymentGateway gateway;
    public ListPaymentProvidersHandler(PaymentAccess access, PaymentGateway gateway) {
        this.access = access;
        this.gateway = gateway;
    }
    public java.util.List<PaymentGateway.Provider> handle(ListPaymentProvidersQuery q) {
        access.read();
        return gateway.providers();
    }
}

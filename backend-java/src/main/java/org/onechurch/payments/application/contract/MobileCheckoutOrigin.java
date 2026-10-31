package org.onechurch.payments.application.contract;
import org.onechurch.payments.domain.PaymentOrder;
public interface MobileCheckoutOrigin {
    String backend(PaymentOrder order);
}

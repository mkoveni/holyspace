package org.onechurch.payments.domain.repository;
import org.onechurch.payments.domain.PaymentReceipt;
public interface PaymentReceiptRepository {
    void insert(PaymentReceipt receipt);
}

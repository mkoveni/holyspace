package org.onechurch.payments.application.contract;
import org.onechurch.payments.domain.PaymentReceipt;

import java.util.Optional;
import java.util.UUID;
public interface PaymentReceiptReader {
    Optional<PaymentReceipt> receipt(UUID id);
    Optional<PaymentReceipt> forCharge(UUID id);
}

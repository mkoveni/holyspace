package org.onechurch.payments.application.contract;
import org.onechurch.payments.domain.PaymentOrder;

import java.time.LocalDate;
import java.util.List;
import java.util.Map;
import java.util.UUID;
public interface PaymentGateway {
    record Provider(String id, String name, boolean available, String mode, boolean recurring) {}
    record Checkout(String id, String url, Map<String, String> fields) {}
    record Notification(String provider, String mode, String eventId, String type, String reference, String checkoutId,
        UUID orderId, long amountMinor, String currency, String token, LocalDate date) {
        @Override
        public String toString() {
            return "Notification[provider=" + provider + ", eventId=" + eventId + "]";
        }
    }
    List<Provider> providers();
    String mode(String provider);
    default void validateOrder(PaymentOrder order) {
    }
    Checkout checkout(PaymentOrder order, String donorName, String email);
    Notification verify(String provider, String rawBody, Map<String, String> headers, String ip);
    String subscription(PaymentOrder order, String token, boolean cancel);
}

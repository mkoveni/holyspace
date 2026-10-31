package org.onechurch.payments.domain;
import java.time.Instant;
import java.util.UUID;
public record MobileCheckout(String tokenHash, UUID orderId, UUID accountId, UUID sessionId, String encryptedCheckout,
    Instant expiresAt, Instant usedAt) {
    @Override
    public String toString() {
        return "MobileCheckout[redacted]";
    }
}

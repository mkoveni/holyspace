package org.onechurch.payments.domain;
import java.time.Instant;
import java.util.UUID;
public record PaymentOrder(UUID id, UUID donorId, UUID fundId, long amountMinor, String provider, String mode,
    boolean recurring, String status, String checkoutId, String checkoutUrl, String checkoutFields,
    String subscriptionStatus, String encryptedToken, String tokenHash, String reviewReason, long version,
    String requestHash, UUID createdBy, Instant createdAt, UUID configurationId, boolean selfService) {
    public PaymentOrder(UUID id, UUID donorId, UUID fundId, long amountMinor, String provider, String mode,
        boolean recurring, String status, String checkoutId, String checkoutUrl, String checkoutFields,
        String subscriptionStatus, String encryptedToken, String tokenHash, String reviewReason, long version,
        String requestHash, UUID createdBy, Instant createdAt, UUID configurationId) {
        this(id, donorId, fundId, amountMinor, provider, mode, recurring, status, checkoutId, checkoutUrl,
            checkoutFields, subscriptionStatus, encryptedToken, tokenHash, reviewReason, version, requestHash,
            createdBy, createdAt, configurationId, false);
    }
    public PaymentOrder(UUID id, UUID donorId, UUID fundId, long amountMinor, String provider, String mode,
        boolean recurring, String status, String checkoutId, String checkoutUrl, String checkoutFields,
        String subscriptionStatus, String encryptedToken, String tokenHash, String reviewReason, long version,
        String requestHash, UUID createdBy, Instant createdAt) {
        this(id, donorId, fundId, amountMinor, provider, mode, recurring, status, checkoutId, checkoutUrl,
            checkoutFields, subscriptionStatus, encryptedToken, tokenHash, reviewReason, version, requestHash,
            createdBy, createdAt, null);
    }
    public PaymentOrder {
        if (id == null || donorId == null || fundId == null || createdBy == null || createdAt == null) {
            throw PaymentException.invalid("Choose a donor and fund.");
        }
        amount(amountMinor);
        if (provider == null || mode == null || !java.util.List.of("payfast", "yoco").contains(provider)
            || !java.util.List.of("test", "live").contains(mode)) {
            throw PaymentException.invalid("Choose a configured payment provider.");
        }
        if (status == null || !java.util.List.of("PENDING", "READY", "PAID", "FAILED").contains(status)
            || subscriptionStatus == null
            || !java.util.List.of("NONE", "PENDING", "ACTIVE", "PAUSED", "LOCKED", "CANCEL_REQUESTED", "CANCELLED")
                .contains(subscriptionStatus)
            || version < 0) {
            throw PaymentException.invalid("Invalid payment lifecycle state.");
        }
        if (recurring && !provider.equals("payfast")) {
            throw PaymentException.invalid("Monthly giving uses Payfast.");
        }
    }
    public static void amount(long amount) {
        if (amount < 500 || amount > 100000000) {
            throw PaymentException.invalid("Enter ZAR 5.00 to 1,000,000.00.");
        }
    }
    public static String text(String value, int maximum, String label) {
        if (value == null || value.isBlank() || value.trim().length() > maximum) {
            throw PaymentException.invalid(label + " is required (at most " + maximum + " characters).");
        }
        return value.trim();
    }
    public void expected(long expected) {
        if (version != expected) {
            throw PaymentException.conflict("This payment changed. Refresh before trying again.");
        }
    }
    @Override
    public String toString() {
        return "PaymentOrder[id=" + id + ", status=" + status + "]";
    }
}

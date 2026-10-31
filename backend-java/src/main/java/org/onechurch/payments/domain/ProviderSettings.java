package org.onechurch.payments.domain;
public record ProviderSettings(String provider, String mode, boolean enabled, boolean monthly, boolean managed,
    long version) {
    public ProviderSettings {
        validate(provider, mode);
        if (monthly && !provider.equals("payfast")) {
            throw PaymentException.invalid("Monthly giving is available through Payfast only.");
        }
    }
    public static void validate(String provider, String mode) {
        if (provider == null || !java.util.List.of("payfast", "yoco").contains(provider) || mode == null
            || !java.util.List.of("test", "live").contains(mode)) {
            throw PaymentException.invalid("Choose Payfast or Yoco and a test or live environment.");
        }
    }
    public void expected(long expected) {
        if (version != expected) {
            throw PaymentException
                .conflict("Payment settings changed. Reload before saving or checking the connection.");
        }
    }
}

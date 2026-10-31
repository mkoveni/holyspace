package org.onechurch.payments.domain;

import java.util.Base64;
import java.util.Optional;
public record ProviderCredentials(String merchantId, String merchantKey, String passphrase, String yocoKey,
    String webhookSecret) {
    public ProviderCredentials {
        merchantId = clean(merchantId);
        merchantKey = clean(merchantKey);
        passphrase = clean(passphrase);
        yocoKey = clean(yocoKey);
        webhookSecret = clean(webhookSecret);
    }
    private static String clean(String value) {
        if (value == null) {
            return "";
        }
        if (value.length() > 1000 || value.chars().anyMatch(c -> c < 32 || c == 127)) {
            throw PaymentException.invalid("Payment credentials contain unsupported characters or are too long.");
        }
        return value.trim();
    }
    public ProviderCredentials replace(ProviderCredentials input) {
        return new ProviderCredentials(input.merchantId.isEmpty() ? merchantId : input.merchantId,
            input.merchantKey.isEmpty() ? merchantKey : input.merchantKey,
            input.passphrase.isEmpty() ? passphrase : input.passphrase,
            input.yocoKey.isEmpty() ? yocoKey : input.yocoKey,
            input.webhookSecret.isEmpty() ? webhookSecret : input.webhookSecret);
    }
    public boolean complete(String provider, String mode) {
        return validationIssue(provider, mode).isEmpty();
    }
    public Optional<String> validationIssue(String provider, String mode) {
        if (provider.equals("payfast")) {
            if (!merchantId.matches("[0-9]{8}")) {
                return Optional.of("Enter the eight-digit Payfast merchant ID.");
            }
            if (merchantKey.isBlank() || passphrase.isBlank()) {
                return Optional.of("Enter the Payfast merchant key and passphrase.");
            }
            return Optional.empty();
        }
        if (!yocoKey.startsWith("sk_" + mode + "_")) {
            return Optional.of("Enter a Yoco secret API key for " + mode + " mode (sk_" + mode
                + "_...). Public keys cannot authenticate checkout requests.");
        }
        if (!webhookSecret.startsWith("whsec_")) {
            return Optional
                .of("Enter the Yoco webhook signing secret (whsec_...) returned when registering the callback URL. "
                    + "This is separate from the API key.");
        }
        try {
            if (Base64.getDecoder().decode(webhookSecret.substring(6)).length >= 16) {
                return Optional.empty();
            }
        } catch (IllegalArgumentException error) {
            // Malformed signing material must never approve a connection.
        }
        return Optional.of("The Yoco webhook signing secret is malformed. Copy the complete whsec_... value "
            + "returned when registering the callback URL, including any trailing = characters.");
    }
    @Override
    public String toString() {
        return "ProviderCredentials[redacted]";
    }
}

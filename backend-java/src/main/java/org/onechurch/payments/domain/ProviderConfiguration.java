package org.onechurch.payments.domain;
import java.net.URI;
import java.time.Instant;
import java.util.UUID;
public record ProviderConfiguration(UUID id, String provider, String mode, long sequence, String publicUrl,
    String ipRanges, String encryptedCredentials, String credentialHash, boolean merchantIdSet, boolean merchantKeySet,
    boolean passphraseSet, boolean yocoKeySet, boolean webhookSecretSet, UUID createdBy, Instant createdAt,
    String backendUrl) {
    public ProviderConfiguration {
        ProviderSettings.validate(provider, mode);
        publicUrl = origin(publicUrl);
        backendUrl = origin(backendUrl == null ? publicUrl : backendUrl);
        ipRanges = ipRanges == null ? "" : ipRanges.trim();
        if (ipRanges.length() > 2000) {
            throw PaymentException.invalid("Too many trusted notification networks.");
        }
    }
    public static String origin(String value) {
        try {
            URI uri = URI.create(value == null ? "" : value.trim());
            String host = uri.getHost();
            if (!"https".equals(uri.getScheme()) || host == null || host.equalsIgnoreCase("localhost")
                || host.equals("127.0.0.1") || host.equals("[::1]") || uri.getUserInfo() != null
                || uri.getQuery() != null || uri.getFragment() != null
                || !(uri.getPath().isEmpty() || uri.getPath().equals("/"))) {
                throw new IllegalArgumentException();
            }
            return uri.toString().replaceAll("/+$", "");
        } catch (IllegalArgumentException error) {
            throw PaymentException.invalid("Use a public HTTPS origin without a path, query or fragment.");
        }
    }
}

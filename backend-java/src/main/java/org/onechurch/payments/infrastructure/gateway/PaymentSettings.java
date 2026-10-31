package org.onechurch.payments.infrastructure.gateway;
import org.onechurch.payments.domain.PaymentException;
import org.springframework.beans.factory.annotation.Value;
import org.springframework.stereotype.Component;

import java.net.URI;
@Component
public final class PaymentSettings {
    final String publicUrl, backendUrl, payfastMode, merchantId, merchantKey, passphrase, ipRanges, yocoMode, yocoKey,
        yocoWebhookSecret, vaultKey;
    public PaymentSettings(@Value("${PAYMENTS_PUBLIC_URL:}") String publicUrl,
        @Value("${PAYFAST_MODE:test}") String payfastMode, @Value("${PAYFAST_MERCHANT_ID:}") String merchantId,
        @Value("${PAYFAST_MERCHANT_KEY:}") String merchantKey, @Value("${PAYFAST_PASSPHRASE:}") String passphrase,
        @Value("${PAYFAST_ITN_IP_RANGES:197.97.145.144/28,41.74.179.192/27}") String ipRanges,
        @Value("${YOCO_MODE:test}") String yocoMode, @Value("${YOCO_SECRET_KEY:}") String yocoKey,
        @Value("${YOCO_WEBHOOK_SECRET:}") String webhookSecret, @Value("${PAYMENTS_TOKEN_KEY:}") String vaultKey,
        @Value("${PAYMENTS_BACKEND_URL:${PAYMENTS_PUBLIC_URL:}}") String backendUrl) {
        this.publicUrl = publicUrl;
        this.backendUrl = backendUrl;
        this.payfastMode = payfastMode;
        this.merchantId = merchantId;
        this.merchantKey = merchantKey;
        this.passphrase = passphrase;
        this.ipRanges = ipRanges;
        this.yocoMode = yocoMode;
        this.yocoKey = yocoKey;
        this.yocoWebhookSecret = webhookSecret;
        this.vaultKey = vaultKey;
    }
    boolean available(String provider) {
        return validBase(publicUrl) && validBase(backendUrl)
            && (provider.equals("payfast")
                ? java.util.List.of("test", "live").contains(payfastMode) && !merchantId.isBlank()
                    && !merchantKey.isBlank() && !passphrase.isBlank() && !ipRanges.isBlank() && validVault()
                : provider.equals("yoco") && java.util.List.of("test", "live").contains(yocoMode)
                    && yocoKey.startsWith("sk_" + yocoMode + "_") && yocoWebhookSecret.startsWith("whsec_"));
    }
    boolean validVault() {
        try {
            return java.util.Base64.getDecoder().decode(vaultKey).length == 32;
        } catch (IllegalArgumentException e) {
            return false;
        }
    }
    private boolean validBase(String value) {
        try {
            URI uri = URI.create(value);
            return "https".equals(uri.getScheme()) && uri.getHost() != null && uri.getUserInfo() == null
                && uri.getQuery() == null && uri.getFragment() == null
                && (uri.getPath().isEmpty() || uri.getPath().equals("/"));
        } catch (IllegalArgumentException e) {
            return false;
        }
    }
    String backendBase() {
        if (!validBase(backendUrl)) {
            throw PaymentException.unavailable("Configure the public HTTPS backend URL.");
        }
        return backendUrl.replaceAll("/+$", "");
    }
    String base() {
        if (!validBase(publicUrl)) {
            throw PaymentException.unavailable("Configure the public HTTPS frontend URL.");
        }
        return publicUrl.replaceAll("/+$", "");
    }
}

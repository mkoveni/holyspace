package org.onechurch.payments.infrastructure.gateway;
import static org.assertj.core.api.Assertions.assertThat;
import static org.assertj.core.api.Assertions.assertThatThrownBy;

import org.junit.jupiter.api.Test;
import org.onechurch.payments.application.contract.PaymentGateway;
import org.onechurch.payments.domain.PaymentException;
import org.onechurch.payments.domain.PaymentOrder;

import java.nio.charset.StandardCharsets;
import java.time.Clock;
import java.time.Instant;
import java.time.ZoneId;
import java.time.ZoneOffset;
import java.util.Base64;
import java.util.LinkedHashMap;
import java.util.Map;
import java.util.UUID;

import javax.crypto.Mac;
import javax.crypto.spec.SecretKeySpec;
class HostedPaymentGatewayTest {
    private static final Clock CLOCK = Clock.fixed(Instant.parse("2026-10-28T10:00:00Z"), ZoneOffset.UTC);
    private static final String SECRET =
        Base64.getEncoder().encodeToString("test-webhook-secret-32-characters".getBytes(StandardCharsets.UTF_8));
    private PaymentSettings settings() {
        return new PaymentSettings("https://church.example", "test", "10000100", "merchant-key", "passphrase",
            "127.0.0.1/32", "test", "sk_test_example", "whsec_" + SECRET,
            Base64.getEncoder().encodeToString(new byte[32]), "https://api.church.example");
    }
    private HostedPaymentGateway gateway(ProviderHttp http) {
        return new HostedPaymentGateway(settings(), CLOCK, ZoneId.of("Africa/Johannesburg"), http);
    }
    private PaymentOrder order(boolean recurring) {
        return new PaymentOrder(UUID.randomUUID(), UUID.randomUUID(), UUID.randomUUID(), 10000, "payfast", "test",
            recurring, "PENDING", null, null, null, recurring ? "PENDING" : "NONE", null, null, null, 0, "h".repeat(64),
            UUID.randomUUID(), CLOCK.instant());
    }
    private String event() {
        return "{\"id\":\"evt_one\",\"type\":\"payment.succeeded\",\"payload\":{\"id\":\"p_one\",\"amount\":10000,\"currency\":\"ZAR\",\"mode\":\"test\",\"status\":\"succeeded\",\"createdDate\":\"2026-10-28T09:00:00Z\",\"metadata\":{\"checkoutId\":\"co_one\"}}}";
    }
    private Map<String, String> headers(String body, long timestamp) throws Exception {
        Mac mac = Mac.getInstance("HmacSHA256");
        mac.init(new SecretKeySpec(Base64.getDecoder().decode(SECRET), "HmacSHA256"));
        String signature = Base64.getEncoder()
            .encodeToString(mac.doFinal(("msg_one." + timestamp + "." + body).getBytes(StandardCharsets.UTF_8)));
        return Map.of("webhook-id", "msg_one", "webhook-timestamp", Long.toString(timestamp), "webhook-signature",
            "v1,invalid v1," + signature);
    }
    @Test
    void donorCheckoutReturnsToMyGivingAndKeepsBackendCallbacksSeparate() {
        PaymentOrder order = new PaymentOrder(UUID.randomUUID(), UUID.randomUUID(), UUID.randomUUID(), 10000, "payfast",
            "test", false, "PENDING", null, null, null, "NONE", null, null, null, 0, "h".repeat(64), UUID.randomUUID(),
            CLOCK.instant(), null, true);
        PaymentGateway.Checkout checkout = gateway((method, url, headers, body) -> {
            throw new AssertionError("Hosted form requires no network calls");
        }).checkout(order, "Donor", "donor@example.org");
        assertThat(checkout.fields().get("return_url"))
            .isEqualTo("https://church.example/my-giving?order=" + order.id());
        assertThat(checkout.fields().get("notify_url"))
            .isEqualTo("https://api.church.example/api/payments/webhooks/payfast");
    }
    @Test
    void yocoReturnsTheBrowserToTheFrontendWhenTheBackendHasADifferentOrigin() {
        PaymentOrder order = new PaymentOrder(UUID.randomUUID(), UUID.randomUUID(), UUID.randomUUID(), 10000, "yoco",
            "test", false, "PENDING", null, null, null, "NONE", null, null, null, 0, "h".repeat(64), UUID.randomUUID(),
            CLOCK.instant());
        HostedPaymentGateway gateway = gateway((method, url, headers, body) -> {
            assertThat(body).contains("https://church.example/payments?order=" + order.id())
                .doesNotContain("https://api.church.example");
            return "{\"id\":\"co_test\",\"amount\":10000,\"currency\":\"ZAR\",\"processingMode\":\"test\","
                + "\"redirectUrl\":\"https://c.yoco.com/checkout/test\"}";
        });
        assertThat(gateway.checkout(order, "Donor", "donor@example.org").url())
            .isEqualTo("https://c.yoco.com/checkout/test");
    }
    @Test
    void verifiesRawBodyAndTimestampBeforeParsing() throws Exception {
        HostedPaymentGateway gateway = gateway((m, u, h, b) -> {
            throw new AssertionError("Yoco verification must not make network calls");
        });
        String body = event();
        PaymentGateway.Notification notification =
            gateway.verify("yoco", body, headers(body, CLOCK.instant().getEpochSecond()), "127.0.0.1");
        assertThat(notification.amountMinor()).isEqualTo(10000);
        assertThat(notification.type()).isEqualTo("PAID");
        assertThatThrownBy(
            () -> gateway.verify("yoco", body + " ", headers(body, CLOCK.instant().getEpochSecond()), "127.0.0.1"))
            .isInstanceOf(PaymentException.class);
        assertThatThrownBy(
            () -> gateway.verify("yoco", body, headers(body, CLOCK.instant().getEpochSecond() - 181), "127.0.0.1"))
            .isInstanceOf(PaymentException.class);
    }
    @Test
    void rejectsModeCurrencyStatusAndFractionalAmountTampering() throws Exception {
        HostedPaymentGateway gateway = gateway((m, u, h, b) -> "");
        for (String changed : java.util.List.of(event().replace("10000", "10000.5"),
            event().replace("\"test\"", "\"live\""), event().replace("\"succeeded\"", "\"failed\""))) {
            assertThatThrownBy(
                () -> gateway.verify("yoco", changed, headers(changed, CLOCK.instant().getEpochSecond()), "127.0.0.1"))
                .isInstanceOf(PaymentException.class);
        }
    }
    @Test
    void payfastUsesSignedOrderedFormAndIndependentServerValidation() {
        HostedPaymentGateway gateway = gateway((method, url, headers, body) -> {
            assertThat(url).isEqualTo("https://sandbox.payfast.co.za/eng/query/validate");
            return "VALID";
        });
        Map<String, String> values = new LinkedHashMap<>();
        values.put("merchant_id", "10000100");
        values.put("m_payment_id", UUID.randomUUID().toString());
        values.put("pf_payment_id", "12345");
        values.put("payment_status", "COMPLETE");
        values.put("amount_gross", "100.00");
        values.put("item_description", "");
        String encoded = values.entrySet()
            .stream()
            .map(e -> e.getKey() + "=" + java.net.URLEncoder.encode(e.getValue(), StandardCharsets.UTF_8))
            .collect(java.util.stream.Collectors.joining("&"));
        String body = encoded + "&signature=" + HostedPaymentGateway.formSignature(values, "passphrase");
        // Empty fields are part of an ITN signature; compute the provider signature independently.
        try {
            body = encoded + "&signature="
                + java.util.HexFormat.of()
                    .formatHex(java.security.MessageDigest.getInstance("MD5")
                        .digest((encoded + "&passphrase=passphrase").getBytes(StandardCharsets.UTF_8)));
        } catch (java.security.GeneralSecurityException e) {
            throw new IllegalStateException(e);
        }
        assertThat(gateway.verify("payfast", body, Map.of(), "127.0.0.1").reference()).isEqualTo("12345");
        String signed = body;
        assertThatThrownBy(() -> gateway.verify("payfast", signed, Map.of(), "192.0.2.1"))
            .isInstanceOf(PaymentException.class);
        HostedPaymentGateway invalid = gateway((m, u, h, b) -> "INVALID");
        assertThatThrownBy(() -> invalid.verify("payfast", signed, Map.of(), "127.0.0.1"))
            .isInstanceOf(PaymentException.class);
    }
    @Test
    void monthlyCheckoutUsesNextMonthAndKeepsPassphrasePrivate() {
        PaymentGateway.Checkout checkout = gateway((m, u, h, b) -> {
            throw new AssertionError();
        }).checkout(order(true), "Donor", "donor@example.org");
        assertThat(checkout.fields())
            .containsEntry("return_url",
                "https://church.example/payments?order=" + checkout.fields().get("m_payment_id"))
            .containsEntry("notify_url", "https://api.church.example/api/payments/webhooks/payfast")
            .containsEntry("billing_date", "2026-11-28")
            .containsEntry("frequency", "3")
            .containsEntry("amount", "100.00");
        assertThat(checkout.fields()).doesNotContainKey("passphrase");
        assertThat(checkout.fields().keySet().stream().toList().getLast()).isEqualTo("signature");
    }
    @Test
    void checkoutAddressesCannotEscapeProviderHost() {
        assertThat(HostedPaymentGateway.validYocoUrl("https://c.yoco.com/checkout/one")).isTrue();
        for (String value : java.util.List.of("http://c.yoco.com/one", "https://c.yoco.com.evil.test/one",
            "https://evil.test@c.yoco.com/one", "https://c.yoco.com:444/one")) {
            assertThat(HostedPaymentGateway.validYocoUrl(value)).isFalse();
        }
    }
    @Test
    void tokensAreAuthenticatedEncryptedAndRedacted() {
        AesSubscriptionVault vault = new AesSubscriptionVault(settings());
        String token = "12345678-1234-1234-1234-123456789012";
        String encrypted = vault.encrypt(token);
        assertThat(encrypted).doesNotContain(token);
        assertThat(vault.decrypt(encrypted)).isEqualTo(token);
        assertThat(vault.encrypt(token)).isNotEqualTo(encrypted);
        assertThatThrownBy(() -> vault.decrypt(encrypted + "broken")).isInstanceOf(PaymentException.class);
        assertThat(order(true).toString()).doesNotContain("encryptedToken", "merchant-key");
    }
}

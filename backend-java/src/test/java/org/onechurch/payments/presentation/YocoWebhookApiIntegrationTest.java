package org.onechurch.payments.presentation;
import static org.assertj.core.api.Assertions.assertThat;
import static org.onechurch.shared.infrastructure.jooq.Tables.GIVING_GIFTS;
import static org.onechurch.shared.infrastructure.jooq.Tables.GIVING_PAYMENT_REFUNDS;

import org.junit.jupiter.api.Test;
import org.onechurch.payments.domain.repository.PaymentRepository;
import org.onechurch.shared.application.UnitOfWork;
import org.onechurch.shared.infrastructure.PaymentIntegrationTestSupport;
import org.springframework.beans.factory.annotation.Autowired;
import org.springframework.test.context.TestPropertySource;

import io.restassured.RestAssured;

import java.nio.charset.StandardCharsets;
import java.time.Instant;
import java.util.Base64;
import java.util.Map;
import java.util.UUID;

import javax.crypto.Mac;
import javax.crypto.spec.SecretKeySpec;
@TestPropertySource(properties = {"PAYMENTS_PUBLIC_URL=https://church.example", "YOCO_MODE=live",
    "YOCO_SECRET_KEY=sk_live_fixture", "YOCO_WEBHOOK_SECRET=whsec_dGVzdC13ZWJob29rLXNlY3JldC0zMi1jaGFyYWN0ZXJz"})
class YocoWebhookApiIntegrationTest extends PaymentIntegrationTestSupport {
    @Autowired PaymentRepository repository;
    @Autowired UnitOfWork tx;
    private UUID order() throws Exception {
        return created(request("POST", "/api/payments", admin, Map.of("requestId", UUID.randomUUID(), "donorId",
            person("Donor"), "fundId", fund("General"), "amountMinor", 10000, "provider", "yoco", "recurring", false)));
    }
    private String body(UUID id, String type, String reference, int amount) {
        return "{\"id\":\"evt_" + reference + "\",\"type\":\"" + type + "\",\"payload\":{\"id\":\"" + reference
            + "\",\"amount\":" + amount
            + ",\"currency\":\"ZAR\",\"mode\":\"live\",\"status\":\"succeeded\",\"createdDate\":\"" + Instant.now()
            + "\",\"metadata\":{\"checkoutId\":\"co_one\",\"orderId\":\"" + id + "\"}}}";
    }
    private int callback(String body, boolean valid) throws Exception {
        String timestamp = Long.toString(Instant.now().getEpochSecond());
        Mac mac = Mac.getInstance("HmacSHA256");
        mac.init(new SecretKeySpec("test-webhook-secret-32-characters".getBytes(StandardCharsets.UTF_8), "HmacSHA256"));
        String signature = Base64.getEncoder()
            .encodeToString(mac.doFinal(("msg_one." + timestamp + "." + body).getBytes(StandardCharsets.UTF_8)));
        return RestAssured.given()
            .baseUri("http://localhost")
            .port(port)
            .contentType("application/json")
            .header("webhook-id", "msg_one")
            .header("webhook-timestamp", timestamp)
            .header("webhook-signature", "v1," + (valid ? signature : "invalid"))
            .body(body)
            .post("/api/payments/webhooks/yoco")
            .statusCode();
    }
    @Test
    void rawSignedHttpNotificationsAreRequiredAndDeduplicateGiftPosting() throws Exception {
        UUID id = order();
        tx.execute(() -> {
            repository.lock(id).orElseThrow();
            repository.ready(id, "co_one", "https://c.yoco.com/checkout/one", "{}");
            return null;
        });
        String paid = body(id, "payment.succeeded", "payment_one", 10000);
        assertThat(callback(paid, false)).isEqualTo(400);
        assertThat(db.fetchCount(GIVING_GIFTS)).isZero();
        assertThat(callback(paid, true)).isEqualTo(204);
        assertThat(callback(paid, true)).isEqualTo(204);
        assertThat(db.fetchCount(GIVING_GIFTS)).isEqualTo(1);
        assertThat(callback(body(id, "refund.succeeded", "refund_one", 2500), true)).isEqualTo(204);
        assertThat(db.fetchCount(GIVING_PAYMENT_REFUNDS)).isEqualTo(1);
    }
    @Test
    void notificationBeforeCheckoutConfirmationReturnsRetryableFailure() throws Exception {
        UUID id = order();
        assertThat(callback(body(id, "payment.succeeded", "payment_early", 10000), true)).isEqualTo(503);
        assertThat(db.fetchCount(GIVING_GIFTS)).isZero();
    }
}

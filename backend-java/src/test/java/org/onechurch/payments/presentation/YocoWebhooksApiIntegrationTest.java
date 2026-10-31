package org.onechurch.payments.presentation;

import static org.assertj.core.api.Assertions.assertThat;
import static org.mockito.ArgumentMatchers.any;
import static org.mockito.ArgumentMatchers.anyString;
import static org.mockito.Mockito.when;
import static org.onechurch.shared.infrastructure.jooq.Tables.PAYMENTS_CONFIGURATION_REVISIONS;
import static org.onechurch.shared.infrastructure.jooq.Tables.PAYMENTS_PROVIDER_SETTINGS;
import static org.onechurch.shared.infrastructure.jooq.Tables.PAYMENTS_WEBHOOK_OPERATIONS;

import org.junit.jupiter.api.BeforeEach;
import org.junit.jupiter.api.Test;
import org.onechurch.payments.infrastructure.gateway.ProviderHttp;
import org.onechurch.shared.infrastructure.PaymentIntegrationTestSupport;
import org.springframework.test.context.TestPropertySource;
import org.springframework.test.context.bean.override.mockito.MockitoBean;
import org.springframework.transaction.support.TransactionSynchronizationManager;

import tools.jackson.databind.JsonNode;
import tools.jackson.databind.json.JsonMapper;

import java.util.ArrayList;
import java.util.HashMap;
import java.util.List;
import java.util.Map;
import java.util.UUID;
import java.util.concurrent.atomic.AtomicInteger;

@TestPropertySource(properties = {"PAYMENTS_TOKEN_KEY=AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA="})
class YocoWebhooksApiIntegrationTest extends PaymentIntegrationTestSupport {
    @MockitoBean ProviderHttp http;
    private final List<Map<String, String>> subscriptions = new ArrayList<>();
    private final AtomicInteger registrations = new AtomicInteger();
    private final JsonMapper json = JsonMapper.builder().build();
    private boolean loseResponse;
    private boolean changeVersion;
    private static final String SECRET = "whsec_AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA=";
    @BeforeEach
    void provider() {
        subscriptions.clear();
        registrations.set(0);
        loseResponse = false;
        changeVersion = false;
        when(http.send(anyString(), anyString(), any(), anyString())).thenAnswer(call -> {
            assertThat(TransactionSynchronizationManager.isActualTransactionActive()).isFalse();
            String method = call.getArgument(0), url = call.getArgument(1), body = call.getArgument(3);
            if (method.equals("GET")) {
                return json.writeValueAsString(Map.of("subscriptions", subscriptions));
            }
            if (method.equals("DELETE")) {
                subscriptions.removeIf(v -> url.endsWith("/" + v.get("id")));
                return "{}";
            }
            registrations.incrementAndGet();
            JsonNode payload = json.readTree(body);
            Map<String, String> entry = Map.of("id", "sub_fixture", "name", payload.path("name").asText(), "url",
                payload.path("url").asText(), "mode", "test");
            subscriptions.add(entry);
            if (changeVersion) {
                db.update(PAYMENTS_PROVIDER_SETTINGS)
                    .set(PAYMENTS_PROVIDER_SETTINGS.VERSION, 2L)
                    .where(PAYMENTS_PROVIDER_SETTINGS.PROVIDER.eq("yoco"))
                    .execute();
            }
            if (loseResponse) {
                throw new IllegalStateException("provider response containing a secret must not escape");
            }
            Map<String, String> response = new HashMap<>(entry);
            response.put("secret", SECRET);
            return json.writeValueAsString(response);
        });
    }
    private Map<String, Object> settings(long version, boolean enabled) {
        return Map.of("expectedVersion", version, "mode", "test", "enabled", enabled, "monthly", false, "publicUrl",
            "https://church.example", "backendUrl", "https://api.church.example", "credentials",
            Map.of("yocoKey", "sk_test_fixture"), "reason", "Reviewed test setup");
    }
    private void saved() throws Exception {
        assertThat(request("PUT", "/api/payments/settings/yoco", admin, settings(0, false)).status()).isEqualTo(204);
    }
    private Map<String, Object> change(UUID id, long version) {
        return Map.of("requestId", id, "mode", "test", "expectedVersion", version, "reason",
            "Reviewed provider subscriptions");
    }
    @Test
    void anInterruptedOperationCanBeClosedAfterProviderReviewWithoutMakingAnotherRegistration() throws Exception {
        saved();
        loseResponse = true;
        UUID id = UUID.randomUUID();
        request("POST", "/api/payments/settings/yoco/webhooks", admin, change(id, 1));
        db.update(PAYMENTS_WEBHOOK_OPERATIONS)
            .set(PAYMENTS_WEBHOOK_OPERATIONS.STATUS, "STARTED")
            .where(PAYMENTS_WEBHOOK_OPERATIONS.ID.eq(id))
            .execute();
        String path = "/api/payments/settings/yoco/webhook-operations/" + id + "/close";
        Map<String, Object> evidence =
            Map.of("expectedVersion", 1, "reason", "Inspected the provider subscription list after interruption");
        assertThat(request("POST", path, admin, evidence).status()).isEqualTo(409);
        db.update(PAYMENTS_WEBHOOK_OPERATIONS)
            .set(PAYMENTS_WEBHOOK_OPERATIONS.CREATED_AT, java.time.OffsetDateTime.now().minusMinutes(6))
            .where(PAYMENTS_WEBHOOK_OPERATIONS.ID.eq(id))
            .execute();
        assertThat(request("POST", path, admin, evidence).status()).isEqualTo(200);
        assertThat(registrations).hasValue(1);
        assertThat(request("GET", "/api/payments/settings/yoco/webhooks?mode=test", admin, null).body())
            .doesNotContain("STARTED");
    }
    @Test
    void recoveryReasonsCannotPersistCredentialsInConfigurationHistory() throws Exception {
        saved();
        loseResponse = true;
        UUID id = UUID.randomUUID();
        assertThat(request("POST", "/api/payments/settings/yoco/webhooks", admin, change(id, 1)).body())
            .contains("UNKNOWN");
        Response blocked = request("POST", "/api/payments/settings/yoco/webhook-operations/" + id + "/close", admin,
            Map.of("expectedVersion", 1, "reason", "Reviewed sk_test_fixture"));
        assertThat(blocked.status()).isEqualTo(400);
        assertThat(request("GET", "/api/payments/settings", admin, null).body())
            .doesNotContain("Reviewed sk_test_fixture");
    }
    @Test
    void callbacksReferencedByExistingPaymentsCannotBeRemoved() throws Exception {
        saved();
        assertThat(request("POST", "/api/payments/settings/yoco/webhooks", admin, change(UUID.randomUUID(), 1)).body())
            .contains("APPLIED");
        assertThat(request("POST", "/api/payments/settings/yoco/connection-check", admin,
            Map.of("expectedVersion", 2, "mode", "test")).body()).contains("true");
        assertThat(request("PUT", "/api/payments/settings/yoco", admin, settings(2, true)).status()).isEqualTo(204);
        assertThat(request("POST", "/api/payments", admin, Map.of("requestId", UUID.randomUUID(), "donorId",
            person("Donor"), "fundId", fund("General"), "amountMinor", 10000, "provider", "yoco")).status())
            .isEqualTo(201);
        assertThat(request("PUT", "/api/payments/settings/yoco", admin, settings(3, false)).status()).isEqualTo(204);
        Response blocked = request("POST", "/api/payments/settings/yoco/webhooks/sub_fixture/removal", admin,
            change(UUID.randomUUID(), 4));
        assertThat(blocked.status()).withFailMessage(blocked.body()).isEqualTo(409);
        assertThat(subscriptions).hasSize(1);
    }
    @Test
    void registrationStoresTheSigningSecretWithoutReturningItAndRetriesDoNotCreateDuplicates() throws Exception {
        saved();
        UUID id = UUID.randomUUID();
        Response registered = request("POST", "/api/payments/settings/yoco/webhooks", admin, change(id, 1));
        assertThat(registered.status()).withFailMessage(registered.body()).isEqualTo(200);
        assertThat(registered.body()).contains("APPLIED").doesNotContain(SECRET, "sk_test_fixture");
        assertThat(request("POST", "/api/payments/settings/yoco/webhooks", admin, change(id, 2)).body())
            .contains("APPLIED");
        assertThat(registrations).hasValue(1);
        assertThat(db.fetchCount(PAYMENTS_CONFIGURATION_REVISIONS)).isEqualTo(2);
        String encrypted = db.select(PAYMENTS_WEBHOOK_OPERATIONS.ENCRYPTED_SECRET)
            .from(PAYMENTS_WEBHOOK_OPERATIONS)
            .where(PAYMENTS_WEBHOOK_OPERATIONS.ID.eq(id))
            .fetchOne(PAYMENTS_WEBHOOK_OPERATIONS.ENCRYPTED_SECRET);
        assertThat(encrypted).isNotBlank().doesNotContain(SECRET);
        Response list = request("GET", "/api/payments/settings/yoco/webhooks?mode=test", admin, null);
        assertThat(list.body()).contains("https://api.church.example/api/payments/webhooks/yoco")
            .doesNotContain(SECRET, "encryptedSecret");
        assertThat(request("POST", "/api/payments/settings/yoco/connection-check", admin,
            Map.of("expectedVersion", 2, "mode", "test")).body()).contains("\"successful\":true");
    }
    @Test
    void uncertainRegistrationNeverAutomaticallyRegistersAgain() throws Exception {
        saved();
        loseResponse = true;
        UUID id = UUID.randomUUID();
        assertThat(request("POST", "/api/payments/settings/yoco/webhooks", admin, change(id, 1)).body())
            .contains("UNKNOWN");
        assertThat(request("POST", "/api/payments/settings/yoco/webhooks", admin, change(id, 1)).body())
            .contains("UNKNOWN");
        assertThat(registrations).hasValue(1);
        assertThat(request("GET", "/api/payments/settings/yoco/webhooks?mode=test", admin, null).body())
            .contains("sub_fixture", "UNKNOWN");
    }
    @Test
    void aConcurrentSettingsChangeRetainsTheSecretForExplicitApplication() throws Exception {
        saved();
        changeVersion = true;
        UUID id = UUID.randomUUID();
        assertThat(request("POST", "/api/payments/settings/yoco/webhooks", admin, change(id, 1)).body())
            .contains("READY");
        changeVersion = false;
        assertThat(request("POST", "/api/payments/settings/yoco/webhooks", admin, change(id, 2)).body())
            .contains("APPLIED");
        assertThat(registrations).hasValue(1);
    }
    @Test
    void obsoleteSubscriptionsCanBeRemovedWithIndependentConfigurationPermission() throws Exception {
        saved();
        subscriptions.add(Map.of("id", "sub_old", "name", "Old tunnel", "mode", "test", "url",
            "https://old.example/api/payments/webhooks/yoco"));
        Response removed = request("POST", "/api/payments/settings/yoco/webhooks/sub_old/removal", admin,
            change(UUID.randomUUID(), 1));
        assertThat(removed.status()).withFailMessage(removed.body()).isEqualTo(200);
        assertThat(subscriptions).isEmpty();
        assertThat(request("GET", "/api/payments/settings/yoco/webhooks?mode=test", null, null).status())
            .isEqualTo(401);
        revoke("payments.configure");
        assertThat(request("GET", "/api/payments/settings/yoco/webhooks?mode=test", admin, null).status())
            .isEqualTo(403);
    }
}

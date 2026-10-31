package org.onechurch.payments.presentation;
import static org.assertj.core.api.Assertions.assertThat;
import static org.mockito.ArgumentMatchers.any;
import static org.mockito.ArgumentMatchers.anyString;
import static org.mockito.Mockito.verifyNoInteractions;
import static org.mockito.Mockito.when;
import static org.onechurch.shared.infrastructure.jooq.Tables.GIVING_GIFTS;
import static org.onechurch.shared.infrastructure.jooq.Tables.PAYMENTS_CHARGES;
import static org.onechurch.shared.infrastructure.jooq.Tables.PAYMENTS_CONFIGURATION_CHECKS;
import static org.onechurch.shared.infrastructure.jooq.Tables.PAYMENTS_CONFIGURATION_HISTORY;
import static org.onechurch.shared.infrastructure.jooq.Tables.PAYMENTS_CONFIGURATION_REVISIONS;
import static org.onechurch.shared.infrastructure.jooq.Tables.PAYMENTS_ORDERS;

import org.junit.jupiter.api.BeforeEach;
import org.junit.jupiter.api.Test;
import org.onechurch.payments.application.contract.ProviderConnectionProbe;
import org.onechurch.payments.infrastructure.gateway.ProviderHttp;
import org.onechurch.shared.infrastructure.PaymentIntegrationTestSupport;
import org.springframework.test.context.TestPropertySource;
import org.springframework.test.context.bean.override.mockito.MockitoBean;
import org.springframework.transaction.support.TransactionSynchronizationManager;

import io.restassured.RestAssured;

import java.nio.charset.StandardCharsets;
import java.security.MessageDigest;
import java.util.HashMap;
import java.util.HexFormat;
import java.util.LinkedHashMap;
import java.util.Map;
import java.util.UUID;
@TestPropertySource(properties = {"PAYMENTS_TOKEN_KEY=AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA="})
class PaymentSettingsApiIntegrationTest extends PaymentIntegrationTestSupport {
    @MockitoBean ProviderConnectionProbe probe;
    @MockitoBean ProviderHttp http;
    @BeforeEach
    void provider() {
        when(probe.check(any(), any())).thenAnswer(call -> {
            assertThat(TransactionSynchronizationManager.isActualTransactionActive()).isFalse();
            return new ProviderConnectionProbe.Result(true,
                "API authentication confirmed. Callback pilot still required.");
        });
        when(http.send(anyString(), anyString(), any(), anyString())).thenAnswer(call -> {
            assertThat(TransactionSynchronizationManager.isActualTransactionActive()).isFalse();
            String url = call.getArgument(1);
            if (url.contains("/query/validate")) {
                return "VALID";
            }
            if (url.contains("/cancel")) {
                return "{\"status\":\"success\",\"code\":200,\"data\":{\"response\":true}}";
            }
            throw new AssertionError("Unexpected provider endpoint " + url);
        });
    }
    private Map<String, Object> body(String mode, long version, boolean enabled, Map<String, String> credentials) {
        return Map.of("expectedVersion", version, "mode", mode, "enabled", enabled, "monthly", true, "publicUrl",
            "https://church.example", "backendUrl", "https://api.church.example", "ipRanges", "127.0.0.1/32",
            "credentials", credentials, "reason", "Reviewed provider configuration");
    }
    private Map<String, String> credentials(String suffix) {
        return Map.of("merchantId", suffix.equals("old") ? "10000100" : "10000101", "merchantKey",
            "private-merchant-key-" + suffix, "passphrase", "private-passphrase-" + suffix);
    }
    private void save(String mode, long version, boolean enabled, Map<String, String> credentials) throws Exception {
        Response result =
            request("PUT", "/api/payments/settings/payfast", admin, body(mode, version, enabled, credentials));
        assertThat(result.status()).withFailMessage(result.body()).isEqualTo(204);
    }
    private void check(String mode, long version) throws Exception {
        Response result = request("POST", "/api/payments/settings/payfast/connection-check", admin,
            Map.of("expectedVersion", version, "mode", mode));
        assertThat(result.status()).withFailMessage(result.body()).isEqualTo(200);
        assertThat(result.body()).contains("\"successful\":true");
    }
    private void configured() throws Exception {
        save("test", 0, false, credentials("old"));
        check("test", 1);
        save("test", 1, true, Map.of());
    }
    @Test
    void backendUrlIsIndependentValidatedAuditedAndRequiresANewConnectionCheck() throws Exception {
        configured();
        Map<String, Object> changed = new HashMap<>(body("test", 2, false, Map.of()));
        changed.put("backendUrl", "https://new-api.church.example/");
        assertThat(request("PUT", "/api/payments/settings/payfast", admin, changed).status()).isEqualTo(204);
        Response settings = request("GET", "/api/payments/settings", admin, null);
        assertThat(settings.body()).contains("\"publicUrl\":\"https://church.example\"",
            "\"backendUrl\":\"https://new-api.church.example\"", "backend URL");
        assertThat(db.fetchCount(PAYMENTS_CONFIGURATION_REVISIONS)).isEqualTo(2);
        changed.put("expectedVersion", 3);
        changed.put("enabled", true);
        assertThat(request("PUT", "/api/payments/settings/payfast", admin, changed).status()).isEqualTo(409);
        changed.put("enabled", false);
        for (String invalid : java.util.List.of("", "http://api.church.example", "https://api.church.example/api",
            "https://localhost", "https://user:password@api.church.example", "https://api.church.example?x=1")) {
            changed.put("backendUrl", invalid);
            assertThat(request("PUT", "/api/payments/settings/payfast", admin, changed).status()).isEqualTo(400);
        }
        changed.remove("backendUrl");
        assertThat(request("PUT", "/api/payments/settings/payfast", admin, changed).status()).isEqualTo(204);
        assertThat(db.fetchCount(PAYMENTS_CONFIGURATION_REVISIONS)).isEqualTo(2);
    }
    @Test
    void malformedYocoSigningSecretExplainsTheFieldWithoutCallingTheProviderOrLeakingCredentials() throws Exception {
        String key = "sk_test_private-fixture";
        String secret = "whsec_invalid-signing-fixture";
        Map<String, Object> settings =
            new HashMap<>(body("test", 0, false, Map.of("yocoKey", key, "webhookSecret", secret)));
        settings.put("monthly", false);
        assertThat(request("PUT", "/api/payments/settings/yoco", admin, settings).status()).isEqualTo(204);
        Response check = request("POST", "/api/payments/settings/yoco/connection-check", admin,
            Map.of("expectedVersion", 1, "mode", "test"));
        assertThat(check.status()).isEqualTo(200);
        assertThat(check.body()).contains("\"successful\":false", "webhook signing secret is malformed")
            .doesNotContain(key, secret);
        verifyNoInteractions(probe);
        Response history = request("GET", "/api/payments/settings", admin, null);
        assertThat(history.body()).contains("webhook signing secret is malformed").doesNotContain(key, secret);
        assertThat(db.fetchCount(PAYMENTS_CONFIGURATION_CHECKS)).isEqualTo(1);
        assertThat(db.fetchCount(PAYMENTS_ORDERS)).isZero();
    }
    @Test
    void settingsAreEncryptedRedactedAndReadsNeverMutate() throws Exception {
        save("test", 0, false, credentials("old"));
        String cipher = db.select(PAYMENTS_CONFIGURATION_REVISIONS.ENCRYPTED_CREDENTIALS)
            .from(PAYMENTS_CONFIGURATION_REVISIONS)
            .fetchOne(PAYMENTS_CONFIGURATION_REVISIONS.ENCRYPTED_CREDENTIALS);
        assertThat(cipher).doesNotContain("private-merchant-key-old", "private-passphrase-old", "10000100");
        Response response = request("GET", "/api/payments/settings", admin, null);
        assertThat(response.status()).isEqualTo(200);
        assertThat(response.body())
            .contains("\"merchantKeySet\":true", "\"passphraseSet\":true", "Church Administrator")
            .doesNotContain("private-merchant-key-old", "private-passphrase-old", "encryptedCredentials", cipher);
        int history = db.fetchCount(PAYMENTS_CONFIGURATION_HISTORY);
        request("GET", "/api/payments/settings", admin, null);
        assertThat(db.fetchCount(PAYMENTS_CONFIGURATION_HISTORY)).isEqualTo(history);
        assertThat(db.fetchCount(PAYMENTS_CONFIGURATION_CHECKS)).isZero();
        assertThat(db.fetchCount(GIVING_GIFTS)).isZero();
        RestAssured.given()
            .baseUri("http://localhost")
            .port(port)
            .header("Authorization", "Bearer " + admin)
            .get("/api/payments/settings")
            .then()
            .header("Cache-Control", org.hamcrest.Matchers.containsString("no-store"));
    }
    @Test
    void activationRequiresASuccessfulCheckForTheSameSavedRevision() throws Exception {
        assertThat(
            request("PUT", "/api/payments/settings/payfast", admin, body("test", 0, true, credentials("old"))).status())
            .isEqualTo(409);
        assertThat(db.fetchCount(PAYMENTS_CONFIGURATION_REVISIONS)).isZero();
        save("test", 0, false, credentials("old"));
        assertThat(request("PUT", "/api/payments/settings/payfast", admin, body("test", 1, true, Map.of())).status())
            .isEqualTo(409);
        check("test", 1);
        save("test", 1, true, Map.of());
        assertThat(request("GET", "/api/payments/providers", admin, null).body()).contains("\"available\":true");
        assertThat(
            request("PUT", "/api/payments/settings/payfast", admin, body("test", 2, true, credentials("new"))).status())
            .isEqualTo(409);
        save("test", 2, false, credentials("new"));
        assertThat(db.fetchCount(PAYMENTS_CONFIGURATION_REVISIONS)).isEqualTo(2);
        assertThat(request("PUT", "/api/payments/settings/payfast", admin, body("test", 3, true, Map.of())).status())
            .isEqualTo(409);
    }
    @Test
    void blankCredentialsPreserveEnvironmentValuesAndVersionsPreventLostUpdates() throws Exception {
        configured();
        save("live", 2, false, credentials("new"));
        check("live", 3);
        save("live", 3, true, Map.of());
        assertThat(db.fetchCount(PAYMENTS_CONFIGURATION_REVISIONS)).isEqualTo(2);
        assertThat(request("PUT", "/api/payments/settings/payfast", admin, body("test", 3, false, Map.of())).status())
            .isEqualTo(409);
        save("test", 4, true, Map.of());
        assertThat(db.fetchCount(PAYMENTS_CONFIGURATION_REVISIONS)).isEqualTo(2);
        assertThat(request("GET", "/api/payments/providers", admin, null).body()).contains("\"mode\":\"test\"");
    }
    @Test
    void settingsPermissionIsSeparateFromPaymentManagement() throws Exception {
        revoke("payments.configure");
        assertThat(request("GET", "/api/payments/settings", admin, null).status()).isEqualTo(403);
        assertThat(request("PUT", "/api/payments/settings/payfast", admin, body("test", 0, false, credentials("old")))
            .status()).isEqualTo(403);
        assertThat(request("POST", "/api/payments/settings/payfast/connection-check", admin,
            Map.of("expectedVersion", 0, "mode", "test")).status()).isEqualTo(403);
        assertThat(request("GET", "/api/payments/settings", null, null).status()).isEqualTo(401);
        assertThat(request("GET", "/api/payments", admin, null).status()).isEqualTo(200);
    }
    @Test
    void connectionFailuresAreSafeAuditedAndDoNotActivateOrCharge() throws Exception {
        save("test", 0, false, credentials("old"));
        when(probe.check(any(), any())).thenThrow(new IllegalStateException("Provider echoed private-passphrase-old"));
        Response check = request("POST", "/api/payments/settings/payfast/connection-check", admin,
            Map.of("expectedVersion", 1, "mode", "test"));
        assertThat(check.status()).isEqualTo(200);
        assertThat(check.body()).contains("\"successful\":false").doesNotContain("private-passphrase-old");
        assertThat(request("GET", "/api/payments/settings", admin, null).body())
            .doesNotContain("private-passphrase-old");
        assertThat(db.fetchCount(PAYMENTS_CONFIGURATION_CHECKS)).isEqualTo(1);
        assertThat(db.fetchCount(GIVING_GIFTS)).isZero();
        assertThat(request("PUT", "/api/payments/settings/payfast", admin, body("test", 1, true, Map.of())).status())
            .isEqualTo(409);
    }
    @Test
    void historicalCheckoutsCallbacksAndSubscriptionsKeepOriginalCredentialsAfterModeSwitch() throws Exception {
        configured();
        UUID donor = person("Donor"), fund = fund("General"),
            id = created(request("POST", "/api/payments", admin, Map.of("requestId", UUID.randomUUID(), "donorId",
                donor, "fundId", fund, "amountMinor", 10000, "provider", "payfast", "recurring", true)));
        UUID revision = db.select(PAYMENTS_ORDERS.CONFIGURATION_ID)
            .from(PAYMENTS_ORDERS)
            .where(PAYMENTS_ORDERS.ID.eq(id))
            .fetchOne(PAYMENTS_ORDERS.CONFIGURATION_ID);
        assertThat(revision).isNotNull();
        Map<String, Object> live = new HashMap<>(body("live", 2, false, credentials("new")));
        live.put("publicUrl", "https://new.church.example");
        live.put("backendUrl", "https://new-api.church.example");
        assertThat(request("PUT", "/api/payments/settings/payfast", admin, live).status()).isEqualTo(204);
        Response checkout = request("POST", "/api/payments/" + id + "/checkout", admin, null);
        assertThat(checkout.status()).isEqualTo(200);
        assertThat(checkout.body())
            .contains("sandbox.payfast.co.za", "10000100", "https://church.example/payments?order=",
                "https://api.church.example/api/payments/webhooks/payfast")
            .doesNotContain("10000101");
        Map<String, String> fields = new LinkedHashMap<>();
        fields.put("merchant_id", "10000100");
        fields.put("m_payment_id", id.toString());
        fields.put("pf_payment_id", "old_charge");
        fields.put("payment_status", "COMPLETE");
        fields.put("amount_gross", "100.00");
        fields.put("token", "12345678-1234-1234-1234-123456789012");
        String encoded = fields.entrySet()
            .stream()
            .map(e -> e.getKey() + "=" + java.net.URLEncoder.encode(e.getValue(), StandardCharsets.UTF_8))
            .collect(java.util.stream.Collectors.joining("&"));
        String signature = HexFormat.of()
            .formatHex(MessageDigest.getInstance("MD5")
                .digest((encoded + "&passphrase=private-passphrase-old").getBytes(StandardCharsets.UTF_8)));
        RestAssured.given()
            .baseUri("http://localhost")
            .port(port)
            .contentType("application/x-www-form-urlencoded")
            .body(encoded + "&signature=" + signature)
            .post("/api/payments/webhooks/payfast")
            .then()
            .statusCode(204);
        assertThat(db.fetchCount(PAYMENTS_CHARGES)).isEqualTo(1);
        assertThat(db.fetchCount(GIVING_GIFTS)).isZero();
        long version = db.select(PAYMENTS_ORDERS.VERSION)
            .from(PAYMENTS_ORDERS)
            .where(PAYMENTS_ORDERS.ID.eq(id))
            .fetchOne(PAYMENTS_ORDERS.VERSION);
        assertThat(request("POST", "/api/payments/" + id + "/subscription", admin,
            Map.of("expectedVersion", version, "cancel", true)).status()).isEqualTo(204);
        assertThat(request("GET", "/api/payments/" + id, admin, null).body()).contains("CANCELLED");
        assertThat(request("POST", "/api/payments", admin, Map.of("requestId", UUID.randomUUID(), "donorId", donor,
            "fundId", fund, "amountMinor", 10000, "provider", "payfast", "recurring", false)).status()).isEqualTo(503);
    }

    @Test
    void configurationPermissionDoesNotRequireOrGrantFinancialReads() throws Exception {
        revoke("payments.manage");
        assertThat(request("GET", "/api/payments/settings", admin, null).status()).isEqualTo(200);
        assertThat(request("GET", "/api/payments", admin, null).status()).isEqualTo(403);
    }
    @Test
    void aConnectionCheckCannotApproveSettingsChangedDuringTheNetworkCall() throws Exception {
        save("test", 0, false, credentials("old"));
        when(probe.check(any(), any())).thenAnswer(call -> {
            db.update(org.onechurch.shared.infrastructure.jooq.Tables.PAYMENTS_PROVIDER_SETTINGS)
                .set(org.onechurch.shared.infrastructure.jooq.Tables.PAYMENTS_PROVIDER_SETTINGS.VERSION, 2L)
                .where(
                    org.onechurch.shared.infrastructure.jooq.Tables.PAYMENTS_PROVIDER_SETTINGS.PROVIDER.eq("payfast"))
                .execute();
            return new ProviderConnectionProbe.Result(true, "API authentication confirmed.");
        });
        assertThat(request("POST", "/api/payments/settings/payfast/connection-check", admin,
            Map.of("expectedVersion", 1, "mode", "test")).status()).isEqualTo(409);
        assertThat(db.fetchCount(PAYMENTS_CONFIGURATION_CHECKS)).isZero();
    }
    @Test
    void publicUrlsAndUnsupportedMonthlyOptionsAreValidated() throws Exception {
        Map<String, Object> bad = new HashMap<>(body("test", 0, false, credentials("old")));
        bad.put("publicUrl", "http://church.example");
        assertThat(request("PUT", "/api/payments/settings/payfast", admin, bad).status()).isEqualTo(400);
        bad.put("publicUrl", "https://church.example/settings");
        assertThat(request("PUT", "/api/payments/settings/payfast", admin, bad).status()).isEqualTo(400);
        bad.put("publicUrl", "https://church.example");
        assertThat(request("PUT", "/api/payments/settings/yoco", admin, bad).status()).isEqualTo(400);
    }
}

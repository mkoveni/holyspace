package org.onechurch.payments.presentation;
import static org.assertj.core.api.Assertions.assertThat;
import static org.mockito.ArgumentMatchers.any;
import static org.mockito.ArgumentMatchers.anyString;
import static org.mockito.Mockito.when;
import static org.onechurch.shared.infrastructure.jooq.Tables.ACCOUNT_SESSIONS;
import static org.onechurch.shared.infrastructure.jooq.Tables.GIVING_DONOR_LINKS;
import static org.onechurch.shared.infrastructure.jooq.Tables.GIVING_GIFTS;
import static org.onechurch.shared.infrastructure.jooq.Tables.PAYMENTS_MOBILE_CHECKOUTS;

import org.junit.jupiter.api.BeforeEach;
import org.junit.jupiter.api.Test;
import org.onechurch.payments.application.contract.MobileCheckoutOrigin;
import org.onechurch.payments.application.contract.PaymentGateway;
import org.onechurch.shared.infrastructure.PaymentIntegrationTestSupport;
import org.springframework.test.context.TestPropertySource;
import org.springframework.test.context.bean.override.mockito.MockitoBean;

import java.net.URI;
import java.time.OffsetDateTime;
import java.util.Map;
import java.util.UUID;
@TestPropertySource(properties = "PAYMENTS_TOKEN_KEY=AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA=")
class MobileCheckoutApiIntegrationTest extends PaymentIntegrationTestSupport {
    @MockitoBean PaymentGateway gateway;
    @MockitoBean MobileCheckoutOrigin origin;
    private UUID account, donor, order;
    private String token;
    @BeforeEach
    void setupCheckout() throws Exception {
        db.truncate(GIVING_DONOR_LINKS).cascade().execute();
        account = createUser("mobile", "mobile@example.org", "Mobile donor");
        token = login("mobile", PASSWORD);
        donor = person("Mobile donor");
        assertThat(request("PUT", "/api/giving/donor-access/" + account, admin,
            Map.of("personId", donor, "active", true, "expectedVersion", 0, "evidence", "Verified donor")).status())
            .isEqualTo(204);
        UUID fund = fund("General");
        assertThat(
            request("PUT", "/api/giving/online-funds/" + fund, admin, Map.of("enabled", true, "expectedVersion", 0))
                .status())
            .isEqualTo(204);
        when(gateway.mode(anyString())).thenReturn("test");
        when(origin.backend(any())).thenReturn("https://api.church.example");
        when(gateway.checkout(any(), anyString(), anyString()))
            .thenReturn(new PaymentGateway.Checkout("checkout", "https://sandbox.payfast.co.za/eng/process",
                Map.of("name_first", "O'Reilly <safe>", "merchant_id", "10000100")));
        order = created(request("POST", "/api/my-giving/payments", token, Map.of("requestId", UUID.randomUUID(),
            "fundId", fund, "amountMinor", 10000, "provider", "payfast", "recurring", false)));
    }
    private String prepare() throws Exception {
        Response response = request("POST", "/api/my-giving/payments/" + order + "/mobile-checkout", token, null);
        assertThat(response.status()).withFailMessage(response.body()).isEqualTo(200);
        assertThat(response.body()).doesNotContain(token, "merchant_id", "encryptedCheckout");
        return URI.create((String) response.data().get("url")).getRawPath();
    }
    private io.restassured.response.Response open(String path, String method) {
        return io.restassured.RestAssured.given()
            .baseUri("http://localhost")
            .port(port)
            .redirects()
            .follow(false)
            .request(method, path);
    }
    @Test
    void browserLandingIsReadOnlyAndCompletingTheHandoffIsSingleUse() throws Exception {
        String path = prepare();
        assertThat(open(path, "GET").statusCode()).isEqualTo(200);
        assertThat(open(path, "GET").statusCode()).isEqualTo(200);
        io.restassured.response.Response opened = open(path, "POST");
        assertThat(opened.statusCode()).isEqualTo(200);
        assertThat(opened.header("Cache-Control")).contains("no-store");
        assertThat(opened.header("Content-Security-Policy")).contains("form-action https://sandbox.payfast.co.za");
        assertThat(opened.asString()).contains("O&#39;Reilly &lt;safe&gt;", "https://sandbox.payfast.co.za/eng/process")
            .doesNotContain(token);
        assertThat(open(path, "POST").statusCode()).isEqualTo(410);
        assertThat(db.fetchCount(GIVING_GIFTS)).isZero();
        assertThat(db.select(PAYMENTS_MOBILE_CHECKOUTS.ENCRYPTED_CHECKOUT)
            .from(PAYMENTS_MOBILE_CHECKOUTS)
            .fetchOne(PAYMENTS_MOBILE_CHECKOUTS.ENCRYPTED_CHECKOUT)).doesNotContain("merchant_id", "O'Reilly");
    }
    @Test
    void expiredAndRevokedSessionCapabilitiesCannotOpenCheckout() throws Exception {
        String path = prepare();
        db.update(PAYMENTS_MOBILE_CHECKOUTS)
            .set(PAYMENTS_MOBILE_CHECKOUTS.EXPIRES_AT, OffsetDateTime.now().minusMinutes(1))
            .execute();
        assertThat(open(path, "POST").statusCode()).isEqualTo(410);
        path = prepare();
        db.deleteFrom(ACCOUNT_SESSIONS).where(ACCOUNT_SESSIONS.ACCOUNT_ID.eq(account)).execute();
        assertThat(open(path, "POST").statusCode()).isEqualTo(410);
    }
    @Test
    void revokingDonorAccessAlsoInvalidatesPreviouslyPreparedHandoffs() throws Exception {
        String path = prepare();
        assertThat(request("PUT", "/api/giving/donor-access/" + account, admin,
            Map.of("personId", donor, "active", false, "expectedVersion", 1, "evidence", "Revoked access")).status())
            .isEqualTo(204);
        assertThat(open(path, "POST").statusCode()).isEqualTo(410);
    }
    @Test
    void donorCannotPrepareAnotherPersonsCheckoutAndYocoRedirectIsBounded() throws Exception {
        UUID other = createUser("other", "other@example.org", "Other"), otherPerson = person("Other");
        String otherToken = login("other", PASSWORD);
        assertThat(request("PUT", "/api/giving/donor-access/" + other, admin,
            Map.of("personId", otherPerson, "active", true, "expectedVersion", 0, "evidence", "Verified other donor"))
            .status()).isEqualTo(204);
        assertThat(request("POST", "/api/my-giving/payments/" + order + "/mobile-checkout", otherToken, null).status())
            .isEqualTo(404);
        when(gateway.checkout(any(), anyString(), anyString()))
            .thenReturn(new PaymentGateway.Checkout("yoco", "https://c.yoco.com/checkout/test", Map.of()));
        String path = prepare();
        io.restassured.response.Response result = open(path, "POST");
        assertThat(result.statusCode()).isEqualTo(303);
        assertThat(result.header("Location")).isEqualTo("https://c.yoco.com/checkout/test");
        assertThat(db.fetchCount(GIVING_GIFTS)).isZero();
    }
}

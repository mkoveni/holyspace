package org.onechurch.payments.infrastructure.gateway;
import static org.assertj.core.api.Assertions.assertThat;
import static org.assertj.core.api.Assertions.assertThatThrownBy;

import org.junit.jupiter.api.Test;
import org.onechurch.payments.domain.PaymentException;
import org.onechurch.payments.domain.ProviderConfiguration;
import org.onechurch.payments.domain.ProviderCredentials;

import java.time.Clock;
import java.time.Instant;
import java.util.UUID;
class HostedProviderConnectionProbeTest {
    private ProviderConfiguration configuration(String provider) {
        return new ProviderConfiguration(UUID.randomUUID(), provider, "test", 1, "https://church.example",
            "127.0.0.1/32", "encrypted", "h".repeat(64), true, true, true, true, true, UUID.randomUUID(), Instant.now(),
            "https://api.church.example");
    }
    @Test
    void aCallbackRegisteredOnTheFrontendDoesNotApproveTheBackendConnection() {
        HostedProviderConnectionProbe probe = new HostedProviderConnectionProbe((method, url, headers,
            body) -> "{\"subscriptions\":[{\"url\":\"https://church.example/api/payments/webhooks/yoco\",\"mode\":\"test\"}]}",
            Clock.systemUTC());
        assertThat(probe
            .check(configuration("yoco"), new ProviderCredentials(null, null, null, "sk_test_fixture", "whsec_fixture"))
            .successful()).isFalse();
    }
    @Test
    void payfastPingUsesAuthenticationWithoutCreatingOrChargingAnything() {
        HostedProviderConnectionProbe probe = new HostedProviderConnectionProbe((method, url, headers, body) -> {
            assertThat(method).isEqualTo("GET");
            assertThat(url).isEqualTo("https://api.payfast.co.za/ping?testing=true");
            assertThat(headers).containsKey("signature").containsEntry("merchant-id", "10000100");
            assertThat(body).isEmpty();
            return "API V1";
        }, Clock.systemUTC());
        assertThat(
            probe.check(configuration("payfast"), new ProviderCredentials("10000100", "key", "passphrase", null, null))
                .successful())
            .isTrue();
    }
    @Test
    void yocoChecksRegisteredCallbackAndEnvironmentWithoutWritingProviderData() {
        HostedProviderConnectionProbe probe = new HostedProviderConnectionProbe((method, url, headers, body) -> {
            assertThat(method).isEqualTo("GET");
            assertThat(url).isEqualTo("https://payments.yoco.com/api/webhooks");
            return "{\"subscriptions\":[{\"url\":\"https://api.church.example/api/payments/webhooks/yoco\",\"mode\":\"test\"}]}";
        }, Clock.systemUTC());
        assertThat(probe
            .check(configuration("yoco"), new ProviderCredentials(null, null, null, "sk_test_fixture", "whsec_fixture"))
            .successful()).isTrue();
    }
    @Test
    void notificationNetworksMustBeExplicitAndRestricted() {
        HostedProviderConnectionProbe probe = new HostedProviderConnectionProbe((m, u, h, b) -> "", Clock.systemUTC());
        probe.validateNetworks("197.97.145.144/28,41.74.179.192/27");
        for (String bad : java.util.List.of("0.0.0.0/0", "0.0.0.0/00", "example.org", "127.0.0.1,", "999.1.1.1/24")) {
            assertThatThrownBy(() -> probe.validateNetworks(bad)).isInstanceOf(PaymentException.class);
        }
    }
}

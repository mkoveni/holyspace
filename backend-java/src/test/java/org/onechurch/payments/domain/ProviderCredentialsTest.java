package org.onechurch.payments.domain;

import static org.assertj.core.api.Assertions.assertThat;

import org.junit.jupiter.api.Test;

class ProviderCredentialsTest {
    private static final String SIGNING_SECRET = "whsec_AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA=";

    @Test
    void acceptsSigningSecretsAndKeepsEnvironmentsSeparate() {
        ProviderCredentials credentials = credentials("sk_test_fixture", SIGNING_SECRET);
        assertThat(credentials.complete("yoco", "test")).isTrue();
        assertThat(credentials.validationIssue("yoco", "test")).isEmpty();
        assertThat(credentials.validationIssue("yoco", "live")).hasValueSatisfying(
            message -> assertThat(message).contains("sk_live_", "live mode").doesNotContain("sk_test_fixture"));
    }

    @Test
    void explainsPublicKeysAndMissingOrMalformedSecretsWithoutEchoingCredentials() {
        assertThat(credentials("pk_test_private-fixture", SIGNING_SECRET).validationIssue("yoco", "test"))
            .hasValueSatisfying(message -> assertThat(message).contains("secret API key", "Public keys")
                .doesNotContain("pk_test_private-fixture"));
        assertThat(credentials("sk_test_fixture", "").validationIssue("yoco", "test"))
            .hasValueSatisfying(message -> assertThat(message).contains("separate from the API key"));
        for (String secret : new String[]{"whsec_invalid-encoding", "whsec_YQ==", "whsec_"}) {
            ProviderCredentials credentials = credentials("sk_test_fixture", secret);
            assertThat(credentials.complete("yoco", "test")).isFalse();
            assertThat(credentials.validationIssue("yoco", "test")).hasValueSatisfying(
                message -> assertThat(message).contains("malformed", "registering the callback URL"));
        }
    }

    private ProviderCredentials credentials(String key, String secret) {
        return new ProviderCredentials(null, null, null, key, secret);
    }
}

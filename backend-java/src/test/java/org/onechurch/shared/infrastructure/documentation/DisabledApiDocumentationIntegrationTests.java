package org.onechurch.shared.infrastructure.documentation;

import static org.assertj.core.api.Assertions.assertThat;

import org.junit.jupiter.api.Test;
import org.onechurch.shared.infrastructure.IdentityIntegrationTestSupport;
import org.springframework.test.context.TestPropertySource;

@TestPropertySource(properties = {"springdoc.api-docs.enabled=false", "scalar.enabled=false"})
class DisabledApiDocumentationIntegrationTests extends IdentityIntegrationTestSupport {

    @Test
    void documentationCanBeDisabledWithoutChangingApiAuthentication() throws Exception {
        assertThat(request("GET", "/docs", null, null).status()).isEqualTo(404);
        assertThat(request("GET", "/v3/api-docs", null, null).status()).isEqualTo(404);
        assertThat(request("GET", "/v3/api-docs.yaml", null, null).status()).isEqualTo(404);
        assertThat(request("GET", "/api/me", null, null).status()).isEqualTo(401);
        assertThat(request("GET", "/api/me", admin, null).status()).isEqualTo(200);
    }
}

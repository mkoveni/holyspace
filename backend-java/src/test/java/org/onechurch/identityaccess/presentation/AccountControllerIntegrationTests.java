package org.onechurch.identityaccess.presentation;

import static org.assertj.core.api.Assertions.assertThat;

import org.junit.jupiter.api.Test;
import org.onechurch.shared.infrastructure.IdentityIntegrationTestSupport;

import java.util.Map;

class AccountControllerIntegrationTests extends IdentityIntegrationTestSupport {

    @Test
    void requiresAuthenticationAndAccountManagementPermission() throws Exception {
        assertThat(request("GET", "/api/me", null, null).status()).isEqualTo(401);
        assertThat(request("GET", "/api/users", "invalid-token", null).status()).isEqualTo(401);
        createUser("helper", "helper@church.org", "Solly Mabasa");
        String helper = login("helper", PASSWORD);
        assertThat(request("GET", "/api/users", helper, null).status()).isEqualTo(403);
        assertThat(request("GET", "/api/roles", helper, null).status()).isEqualTo(403);
        assertThat(request("POST", "/api/permissions", helper, Map.of("code", "people.manage")).status())
            .isEqualTo(403);
        assertThat(request("GET", "/api/me", helper, null).status()).isEqualTo(200);
    }

    @Test
    void validatesQueryConstraintsDeclaredOnTheApiInterface() throws Exception {
        assertThat(request("GET", "/api/users?limit=101", admin, null).status()).isEqualTo(400);
        assertThat(request("GET", "/api/users?limit=0", admin, null).status()).isEqualTo(400);
        assertThat(request("GET", "/api/users", admin, null).status()).isEqualTo(200);
    }

}

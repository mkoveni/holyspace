package org.onechurch.identityaccess.application.command.logout;

import static org.assertj.core.api.Assertions.assertThat;

import org.junit.jupiter.api.Test;
import org.onechurch.identityaccess.application.contract.SessionReader;
import org.onechurch.identityaccess.domain.IdentityException;
import org.onechurch.shared.infrastructure.IdentityIntegrationTestSupport;
import org.springframework.beans.factory.annotation.Autowired;

import java.util.UUID;

class LogoutHandlerIntegrationTests extends IdentityIntegrationTestSupport {

    @Autowired private LogoutHandler handler;
    @Autowired private SessionReader sessions;

    @Test
    void logoutRevokesOnlyTheCurrentSession() throws Exception {
        String second = login("admin", PASSWORD);
        assertThat(request("POST", "/api/logout", admin, null).status()).isEqualTo(204);
        assertThat(request("GET", "/api/me", admin, null).status()).isEqualTo(401);
        assertThat(request("GET", "/api/me", second, null).status()).isEqualTo(200);
    }

    @Test
    void directLogoutRequiresTheCallersOwnCurrentSession() throws Exception {
        UUID helperId = createUser("logout-helper", "logout-helper@church.org", "Logout Helper");
        String helper = login("logout-helper", PASSWORD);
        String otherSession = login("logout-helper", PASSWORD);
        UUID adminSessionId = sessions.find(tokens.hash(admin)).orElseThrow().id();
        UUID helperSessionId = sessions.find(tokens.hash(helper)).orElseThrow().id();
        UUID otherSessionId = sessions.find(tokens.hash(otherSession)).orElseThrow().id();
        assertIdentityFailure(() -> handler.handle(new LogoutCommand(adminId, adminSessionId)),
            IdentityException.Kind.UNAUTHENTICATED);
        asActor(helper, () -> {
            assertIdentityFailure(() -> handler.handle(new LogoutCommand(adminId, adminSessionId)),
                IdentityException.Kind.FORBIDDEN);
            assertIdentityFailure(() -> handler.handle(new LogoutCommand(helperId, otherSessionId)),
                IdentityException.Kind.FORBIDDEN);
            handler.handle(new LogoutCommand(helperId, helperSessionId));
            return null;
        });
        assertThat(request("GET", "/api/me", helper, null).status()).isEqualTo(401);
        assertThat(request("GET", "/api/me", otherSession, null).status()).isEqualTo(200);
        assertThat(request("GET", "/api/me", admin, null).status()).isEqualTo(200);
    }

}

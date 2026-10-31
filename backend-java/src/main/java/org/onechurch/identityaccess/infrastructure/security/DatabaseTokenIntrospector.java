package org.onechurch.identityaccess.infrastructure.security;

import org.onechurch.identityaccess.application.query.authenticatesession.AuthenticateSessionHandler;
import org.onechurch.identityaccess.application.query.authenticatesession.AuthenticateSessionQuery;
import org.onechurch.identityaccess.domain.IdentityException;
import org.springframework.security.core.GrantedAuthority;
import org.springframework.security.core.authority.SimpleGrantedAuthority;
import org.springframework.security.oauth2.core.DefaultOAuth2AuthenticatedPrincipal;
import org.springframework.security.oauth2.core.OAuth2AuthenticatedPrincipal;
import org.springframework.security.oauth2.server.resource.introspection.BadOpaqueTokenException;
import org.springframework.security.oauth2.server.resource.introspection.OpaqueTokenIntrospector;
import org.springframework.stereotype.Component;

import java.util.List;
import java.util.Map;

@Component
public final class DatabaseTokenIntrospector implements OpaqueTokenIntrospector {

    private final AuthenticateSessionHandler authenticate;

    public DatabaseTokenIntrospector(AuthenticateSessionHandler authenticate) {
        this.authenticate = authenticate;
    }

    @Override
    public OAuth2AuthenticatedPrincipal introspect(String token) {
        try {
            AuthenticateSessionHandler.Identity identity = authenticate.handle(new AuthenticateSessionQuery(token));
            List<GrantedAuthority> authorities =
                identity.account().permissions().stream().<GrantedAuthority>map(SimpleGrantedAuthority::new).toList();
            return new DefaultOAuth2AuthenticatedPrincipal(identity.account().id().toString(),
                Map.of("sub", identity.account().id().toString(), "session_id", identity.sessionId().toString()),
                authorities);
        } catch (IdentityException error) {
            throw new BadOpaqueTokenException("Invalid or expired session.");
        }
    }
}

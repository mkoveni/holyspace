package org.onechurch.identityaccess.infrastructure.security;

import org.onechurch.identityaccess.application.contract.CurrentActor;
import org.springframework.security.core.Authentication;
import org.springframework.security.core.context.SecurityContextHolder;
import org.springframework.security.oauth2.core.OAuth2AuthenticatedPrincipal;
import org.springframework.security.oauth2.server.resource.authentication.BearerTokenAuthentication;
import org.springframework.stereotype.Component;

import java.util.Optional;
import java.util.UUID;

@Component
public final class SpringSecurityCurrentActor implements CurrentActor {

    @Override
    public Optional<Actor> current() {
        Authentication authentication = SecurityContextHolder.getContext().getAuthentication();
        if (!(authentication instanceof BearerTokenAuthentication bearer) || !bearer.isAuthenticated()) {
            return Optional.empty();
        }
        if (!(bearer.getPrincipal() instanceof OAuth2AuthenticatedPrincipal principal)) {
            return Optional.empty();
        }
        Object sessionId = principal.getAttribute("session_id");
        if (principal.getName() == null || !(sessionId instanceof String session)) {
            return Optional.empty();
        }
        try {
            return Optional.of(new Actor(UUID.fromString(principal.getName()), UUID.fromString(session)));
        } catch (IllegalArgumentException error) {
            return Optional.empty();
        }
    }
}

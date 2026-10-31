package org.onechurch.childrenministry.sessions.presentation;
import org.onechurch.childrenministry.sessions.application.query.getsessioncloseout.GetSessionCloseoutHandler;
import org.onechurch.childrenministry.sessions.application.query.getsessioncloseout.GetSessionCloseoutQuery;
import org.onechurch.childrenministry.sessions.application.query.getsessioncloseout.SessionCloseout;
import org.onechurch.childrenministry.sessions.presentation.api.SessionCloseoutApi;
import org.springframework.security.access.prepost.PreAuthorize;
import org.springframework.security.oauth2.core.OAuth2AuthenticatedPrincipal;
import org.springframework.web.bind.annotation.RestController;

import java.util.UUID;
@RestController
public class SessionCloseoutController implements SessionCloseoutApi {
    private final GetSessionCloseoutHandler handler;
    public SessionCloseoutController(GetSessionCloseoutHandler handler) {
        this.handler = handler;
    }
    @Override
    @PreAuthorize("hasAuthority('children.manage')")
    public SessionCloseout get(UUID sessionId, OAuth2AuthenticatedPrincipal principal) {
        return handler.handle(new GetSessionCloseoutQuery(UUID.fromString(principal.getName()), sessionId));
    }
}

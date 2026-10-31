package org.onechurch.childrenministry.sessions.presentation;
import org.onechurch.childrenministry.sessions.application.query.getsessionday.GetSessionDayHandler;
import org.onechurch.childrenministry.sessions.application.query.getsessionday.GetSessionDayQuery;
import org.onechurch.childrenministry.sessions.application.query.getsessionday.SessionDayPage;
import org.onechurch.childrenministry.sessions.presentation.api.SessionDayApi;
import org.springframework.security.access.prepost.PreAuthorize;
import org.springframework.security.oauth2.core.OAuth2AuthenticatedPrincipal;
import org.springframework.web.bind.annotation.RestController;

import java.util.UUID;
@RestController
public class SessionDayController implements SessionDayApi {
    private final GetSessionDayHandler handler;
    public SessionDayController(GetSessionDayHandler handler) {
        this.handler = handler;
    }
    @Override
    @PreAuthorize("hasAuthority('children.station')")
    public SessionDayPage get(UUID sessionId, String search, String view, int limit, int offset,
        OAuth2AuthenticatedPrincipal principal) {
        return handler.handle(
            new GetSessionDayQuery(UUID.fromString(principal.getName()), sessionId, search, view, limit, offset));
    }
}

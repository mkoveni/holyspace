package org.onechurch.childrenministry.sessions.presentation;
import org.onechurch.childrenministry.sessions.application.SessionView;
import org.onechurch.childrenministry.sessions.application.StationAttendance;
import org.onechurch.childrenministry.sessions.application.query.getstationroster.GetStationRosterHandler;
import org.onechurch.childrenministry.sessions.application.query.getstationroster.GetStationRosterQuery;
import org.onechurch.childrenministry.sessions.application.query.getstationsession.GetStationSessionHandler;
import org.onechurch.childrenministry.sessions.application.query.getstationsession.GetStationSessionQuery;
import org.onechurch.childrenministry.sessions.application.query.liststationsessions.ListStationSessionsHandler;
import org.onechurch.childrenministry.sessions.application.query.liststationsessions.ListStationSessionsQuery;
import org.onechurch.childrenministry.sessions.presentation.api.StationApi;
import org.springframework.security.access.prepost.PreAuthorize;
import org.springframework.security.oauth2.core.OAuth2AuthenticatedPrincipal;
import org.springframework.web.bind.annotation.RestController;

import java.time.Instant;
import java.util.List;
import java.util.UUID;
@RestController
@PreAuthorize("hasAuthority('children.station')")
public class StationController implements StationApi {
    private final GetStationRosterHandler roster;
    private final ListStationSessionsHandler list;
    private final GetStationSessionHandler get;
    public StationController(GetStationRosterHandler roster, ListStationSessionsHandler list,
        GetStationSessionHandler get) {
        this.roster = roster;
        this.list = list;
        this.get = get;
    }
    @Override
    public List<SessionView> list(Instant from, Instant until, int limit, int offset,
        OAuth2AuthenticatedPrincipal principal) {
        return list
            .handle(new ListStationSessionsQuery(UUID.fromString(principal.getName()), from, until, limit, offset));
    }
    @Override
    public SessionView get(UUID sessionId, OAuth2AuthenticatedPrincipal principal) {
        return get.handle(new GetStationSessionQuery(UUID.fromString(principal.getName()), sessionId));
    }

    @Override
    public List<StationAttendance> roster(UUID sessionId, int limit, int offset, boolean openOnly,
        OAuth2AuthenticatedPrincipal principal) {
        return roster.handle(
            new GetStationRosterQuery(UUID.fromString(principal.getName()), sessionId, limit, offset, openOnly));
    }
}

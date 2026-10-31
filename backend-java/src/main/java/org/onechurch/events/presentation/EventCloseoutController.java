package org.onechurch.events.presentation;
import org.onechurch.events.application.EventReadiness;
import org.onechurch.events.application.command.closeeventoccurrence.CloseEventOccurrenceCommand;
import org.onechurch.events.application.command.closeeventoccurrence.CloseEventOccurrenceHandler;
import org.onechurch.events.application.command.reopeneventcloseout.ReopenEventCloseoutCommand;
import org.onechurch.events.application.command.reopeneventcloseout.ReopenEventCloseoutHandler;
import org.onechurch.events.application.contract.CloseoutReader;
import org.onechurch.events.application.query.geteventcloseouthistory.GetEventCloseoutHistoryHandler;
import org.onechurch.events.application.query.geteventcloseouthistory.GetEventCloseoutHistoryQuery;
import org.onechurch.events.application.query.geteventreadiness.GetEventReadinessHandler;
import org.onechurch.events.application.query.geteventreadiness.GetEventReadinessQuery;
import org.onechurch.events.presentation.api.EventCloseoutApi;
import org.springframework.security.access.prepost.PreAuthorize;
import org.springframework.security.oauth2.core.OAuth2AuthenticatedPrincipal;
import org.springframework.web.bind.annotation.RestController;

import java.util.List;
import java.util.UUID;
@RestController
@PreAuthorize("hasAuthority('events.manage')")
public class EventCloseoutController implements EventCloseoutApi {
    private final GetEventReadinessHandler readiness;
    private final GetEventCloseoutHistoryHandler history;
    private final CloseEventOccurrenceHandler close;
    private final ReopenEventCloseoutHandler reopen;
    public EventCloseoutController(GetEventReadinessHandler readiness, GetEventCloseoutHistoryHandler history,
        CloseEventOccurrenceHandler close, ReopenEventCloseoutHandler reopen) {
        this.readiness = readiness;
        this.history = history;
        this.close = close;
        this.reopen = reopen;
    }
    @Override
    public EventReadiness.View readiness(UUID id) {
        return readiness.handle(new GetEventReadinessQuery(id));
    }
    @Override
    public void close(UUID id, CloseBody b, OAuth2AuthenticatedPrincipal actor) {
        close.handle(new CloseEventOccurrenceCommand(UUID.fromString(actor.getName()), id, b.expectedVersion(),
            b.reviewHash(), b.note(), b.followUp(), b.attendanceReviewed(), b.followUpReviewed()));
    }
    @Override
    public void reopen(UUID id, ReopenBody b, OAuth2AuthenticatedPrincipal actor) {
        reopen.handle(
            new ReopenEventCloseoutCommand(UUID.fromString(actor.getName()), id, b.expectedVersion(), b.reason()));
    }
    @Override
    public List<CloseoutReader.History> history(UUID id, int limit, int offset) {
        return history.handle(new GetEventCloseoutHistoryQuery(id, limit, offset));
    }
}

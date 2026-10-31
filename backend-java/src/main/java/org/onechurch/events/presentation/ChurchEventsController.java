package org.onechurch.events.presentation;
import org.onechurch.events.application.EventViews;
import org.onechurch.events.application.ScheduleReview;
import org.onechurch.events.application.command.createchurchevent.CreateChurchEventCommand;
import org.onechurch.events.application.command.createchurchevent.CreateChurchEventHandler;
import org.onechurch.events.application.command.createeventseries.CreateEventSeriesCommand;
import org.onechurch.events.application.command.createeventseries.CreateEventSeriesHandler;
import org.onechurch.events.application.command.updatechurchevent.UpdateChurchEventCommand;
import org.onechurch.events.application.command.updatechurchevent.UpdateChurchEventHandler;
import org.onechurch.events.application.query.getchurchevent.GetChurchEventHandler;
import org.onechurch.events.application.query.getchurchevent.GetChurchEventQuery;
import org.onechurch.events.application.query.listchurchevents.ListChurchEventsHandler;
import org.onechurch.events.application.query.listchurchevents.ListChurchEventsQuery;
import org.onechurch.events.application.query.previeweventschedule.PreviewEventScheduleHandler;
import org.onechurch.events.application.query.previeweventschedule.PreviewEventScheduleQuery;
import org.onechurch.events.presentation.api.ChurchEventsApi;
import org.springframework.security.access.prepost.PreAuthorize;
import org.springframework.security.oauth2.core.OAuth2AuthenticatedPrincipal;
import org.springframework.web.bind.annotation.RestController;

import java.util.UUID;
@RestController
public class ChurchEventsController implements ChurchEventsApi {
    private final CreateChurchEventHandler createChurchEvent;
    private final UpdateChurchEventHandler updateChurchEvent;
    private final CreateEventSeriesHandler createEventSeries;
    private final PreviewEventScheduleHandler previewEventSchedule;
    private final ListChurchEventsHandler listChurchEvents;
    private final GetChurchEventHandler getChurchEvent;
    public ChurchEventsController(CreateChurchEventHandler createChurchEvent,
        UpdateChurchEventHandler updateChurchEvent, CreateEventSeriesHandler createEventSeries,
        PreviewEventScheduleHandler previewEventSchedule, ListChurchEventsHandler listChurchEvents,
        GetChurchEventHandler getChurchEvent) {
        this.createChurchEvent = createChurchEvent;
        this.updateChurchEvent = updateChurchEvent;
        this.createEventSeries = createEventSeries;
        this.previewEventSchedule = previewEventSchedule;
        this.listChurchEvents = listChurchEvents;
        this.getChurchEvent = getChurchEvent;
    }
    @Override
    @PreAuthorize("hasAuthority('events.manage')")
    public Created createChurchEvent(CreateChurchEventRequest b, OAuth2AuthenticatedPrincipal p) {
        return new Created(createChurchEvent.handle(new CreateChurchEventCommand(UUID.fromString(p.getName()),
            b.requestId(), b.title(), b.type(), b.description(), b.schedule(), b.previewHash())));
    }
    @Override
    @PreAuthorize("hasAuthority('events.manage')")
    public void updateChurchEvent(UUID id, UpdateChurchEventRequest b, OAuth2AuthenticatedPrincipal p) {
        updateChurchEvent.handle(new UpdateChurchEventCommand(UUID.fromString(p.getName()), id, b.expectedVersion(),
            b.title(), b.type(), b.description(), b.active(), b.reason()));
    }
    @Override
    @PreAuthorize("hasAuthority('events.manage')")
    public Created createEventSeries(UUID id, CreateEventSeriesRequest b, OAuth2AuthenticatedPrincipal p) {
        return new Created(createEventSeries.handle(new CreateEventSeriesCommand(UUID.fromString(p.getName()),
            b.requestId(), id, b.expectedVersion(), b.schedule(), b.previewHash())));
    }
    @Override
    @PreAuthorize("hasAuthority('events.read')")
    public ScheduleReview.Preview previewEventSchedule(PreviewEventScheduleRequest b) {
        return previewEventSchedule.handle(new PreviewEventScheduleQuery(b.draft()));
    }
    @Override
    @PreAuthorize("hasAuthority('events.read')")
    public EventViews.Events listChurchEvents(String search, boolean includeArchived, int limit, int offset) {
        return listChurchEvents.handle(new ListChurchEventsQuery(search, includeArchived, limit, offset));
    }
    @Override
    @PreAuthorize("hasAuthority('events.read')")
    public EventViews.Details getChurchEvent(UUID id, int limit, int offset) {
        return getChurchEvent.handle(new GetChurchEventQuery(id, limit, offset));
    }
}

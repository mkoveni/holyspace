package org.onechurch.events.presentation;
import org.onechurch.events.application.EventViews;
import org.onechurch.events.application.command.createeventvenue.CreateEventVenueCommand;
import org.onechurch.events.application.command.createeventvenue.CreateEventVenueHandler;
import org.onechurch.events.application.command.updateeventvenue.UpdateEventVenueCommand;
import org.onechurch.events.application.command.updateeventvenue.UpdateEventVenueHandler;
import org.onechurch.events.application.query.listeventvenues.ListEventVenuesHandler;
import org.onechurch.events.application.query.listeventvenues.ListEventVenuesQuery;
import org.onechurch.events.presentation.api.EventVenuesApi;
import org.springframework.security.access.prepost.PreAuthorize;
import org.springframework.security.oauth2.core.OAuth2AuthenticatedPrincipal;
import org.springframework.web.bind.annotation.RestController;

import java.util.UUID;
@RestController
public class EventVenuesController implements EventVenuesApi {
    private final CreateEventVenueHandler createEventVenue;
    private final UpdateEventVenueHandler updateEventVenue;
    private final ListEventVenuesHandler listEventVenues;
    public EventVenuesController(CreateEventVenueHandler createEventVenue, UpdateEventVenueHandler updateEventVenue,
        ListEventVenuesHandler listEventVenues) {
        this.createEventVenue = createEventVenue;
        this.updateEventVenue = updateEventVenue;
        this.listEventVenues = listEventVenues;
    }
    @Override
    @PreAuthorize("hasAuthority('events.manage')")
    public Created createEventVenue(CreateEventVenueRequest b, OAuth2AuthenticatedPrincipal p) {
        return new Created(createEventVenue
            .handle(new CreateEventVenueCommand(UUID.fromString(p.getName()), b.name(), b.address(), b.exclusive())));
    }
    @Override
    @PreAuthorize("hasAuthority('events.manage')")
    public void updateEventVenue(UUID id, UpdateEventVenueRequest b, OAuth2AuthenticatedPrincipal p) {
        updateEventVenue.handle(new UpdateEventVenueCommand(UUID.fromString(p.getName()), id, b.expectedVersion(),
            b.name(), b.address(), b.active()));
    }
    @Override
    @PreAuthorize("hasAuthority('events.read')")
    public EventViews.Venues listEventVenues(String search, boolean includeArchived, int limit, int offset) {
        return listEventVenues.handle(new ListEventVenuesQuery(search, includeArchived, limit, offset));
    }
}

package org.onechurch.events.presentation;
import org.onechurch.events.application.EventViews;
import org.onechurch.events.application.command.canceleventregistration.CancelEventRegistrationCommand;
import org.onechurch.events.application.command.canceleventregistration.CancelEventRegistrationHandler;
import org.onechurch.events.application.command.configureeventregistration.ConfigureEventRegistrationCommand;
import org.onechurch.events.application.command.configureeventregistration.ConfigureEventRegistrationHandler;
import org.onechurch.events.application.command.registereventperson.RegisterEventPersonCommand;
import org.onechurch.events.application.command.registereventperson.RegisterEventPersonHandler;
import org.onechurch.events.application.contract.EventJournalReader;
import org.onechurch.events.application.query.geteventregistrationhistory.GetEventRegistrationHistoryHandler;
import org.onechurch.events.application.query.geteventregistrationhistory.GetEventRegistrationHistoryQuery;
import org.onechurch.events.application.query.listeventregistrations.ListEventRegistrationsHandler;
import org.onechurch.events.application.query.listeventregistrations.ListEventRegistrationsQuery;
import org.onechurch.events.presentation.api.EventRegistrationsApi;
import org.springframework.security.access.prepost.PreAuthorize;
import org.springframework.security.oauth2.core.OAuth2AuthenticatedPrincipal;
import org.springframework.web.bind.annotation.RestController;

import java.util.List;
import java.util.UUID;
@RestController
public class EventRegistrationsController implements EventRegistrationsApi {
    private final ConfigureEventRegistrationHandler configureEventRegistration;
    private final RegisterEventPersonHandler registerEventPerson;
    private final CancelEventRegistrationHandler cancelEventRegistration;
    private final ListEventRegistrationsHandler listEventRegistrations;
    private final GetEventRegistrationHistoryHandler getEventRegistrationHistory;
    public EventRegistrationsController(ConfigureEventRegistrationHandler configureEventRegistration,
        RegisterEventPersonHandler registerEventPerson, CancelEventRegistrationHandler cancelEventRegistration,
        ListEventRegistrationsHandler listEventRegistrations,
        GetEventRegistrationHistoryHandler getEventRegistrationHistory) {
        this.configureEventRegistration = configureEventRegistration;
        this.registerEventPerson = registerEventPerson;
        this.cancelEventRegistration = cancelEventRegistration;
        this.listEventRegistrations = listEventRegistrations;
        this.getEventRegistrationHistory = getEventRegistrationHistory;
    }
    @Override
    @PreAuthorize("hasAuthority('events.registrations.manage')")
    public void configureEventRegistration(UUID id, ConfigureEventRegistrationRequest b,
        OAuth2AuthenticatedPrincipal p) {
        configureEventRegistration.handle(new ConfigureEventRegistrationCommand(UUID.fromString(p.getName()), id,
            b.expectedVersion(), b.open(), b.capacity(), b.waitlistEnabled(), b.reason()));
    }
    @Override
    @PreAuthorize("hasAuthority('events.registrations.manage')")
    public Created registerEventPerson(UUID id, RegisterEventPersonRequest b, OAuth2AuthenticatedPrincipal p) {
        return new Created(registerEventPerson.handle(new RegisterEventPersonCommand(UUID.fromString(p.getName()),
            b.requestId(), id, b.personId(), b.expectedVersion())));
    }
    @Override
    @PreAuthorize("hasAuthority('events.registrations.manage')")
    public void cancelEventRegistration(UUID id, CancelEventRegistrationRequest b, OAuth2AuthenticatedPrincipal p) {
        cancelEventRegistration.handle(
            new CancelEventRegistrationCommand(UUID.fromString(p.getName()), id, b.expectedVersion(), b.reason()));
    }
    @Override
    @PreAuthorize("hasAuthority('events.registrations.read')")
    public EventViews.Registrations listEventRegistrations(UUID id, String status, int limit, int offset) {
        return listEventRegistrations.handle(new ListEventRegistrationsQuery(id, status, limit, offset));
    }
    @Override
    @PreAuthorize("hasAuthority('events.registrations.read')")
    public List<EventJournalReader.RegistrationEntry> getEventRegistrationHistory(UUID id, int limit, int offset) {
        return getEventRegistrationHistory.handle(new GetEventRegistrationHistoryQuery(id, limit, offset));
    }
}

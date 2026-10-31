package org.onechurch.events.infrastructure;
import org.onechurch.events.application.EventPlanning;
import org.onechurch.events.application.EventRegistrationOperations;
import org.onechurch.events.application.EventViews;
import org.onechurch.events.application.OccurrenceChangeReview;
import org.onechurch.events.application.OccurrenceChanges;
import org.onechurch.events.application.ScheduleReview;
import org.onechurch.events.application.VenueCatalogue;
import org.onechurch.events.application.command.canceleventoccurrences.CancelEventOccurrencesHandler;
import org.onechurch.events.application.command.canceleventregistration.CancelEventRegistrationHandler;
import org.onechurch.events.application.command.configureeventregistration.ConfigureEventRegistrationHandler;
import org.onechurch.events.application.command.createchurchevent.CreateChurchEventHandler;
import org.onechurch.events.application.command.createeventseries.CreateEventSeriesHandler;
import org.onechurch.events.application.command.createeventvenue.CreateEventVenueHandler;
import org.onechurch.events.application.command.registereventperson.RegisterEventPersonHandler;
import org.onechurch.events.application.command.rescheduleeventoccurrences.RescheduleEventOccurrencesHandler;
import org.onechurch.events.application.command.restoreeventoccurrence.RestoreEventOccurrenceHandler;
import org.onechurch.events.application.command.updatechurchevent.UpdateChurchEventHandler;
import org.onechurch.events.application.command.updateeventvenue.UpdateEventVenueHandler;
import org.onechurch.events.application.contract.AttendanceReader;
import org.onechurch.events.application.contract.EventJournal;
import org.onechurch.events.application.contract.EventJournalReader;
import org.onechurch.events.application.contract.EventReader;
import org.onechurch.events.application.contract.OccurrenceMovementPolicy;
import org.onechurch.events.application.contract.OccurrenceReader;
import org.onechurch.events.application.contract.OccurrenceScheduleListener;
import org.onechurch.events.application.contract.RegistrationReader;
import org.onechurch.events.application.contract.VenueReader;
import org.onechurch.events.application.query.getchurchevent.GetChurchEventHandler;
import org.onechurch.events.application.query.geteventoccurrence.GetEventOccurrenceHandler;
import org.onechurch.events.application.query.geteventoccurrencehistory.GetEventOccurrenceHistoryHandler;
import org.onechurch.events.application.query.geteventregistrationhistory.GetEventRegistrationHistoryHandler;
import org.onechurch.events.application.query.listchurchevents.ListChurchEventsHandler;
import org.onechurch.events.application.query.listeventcalendar.ListEventCalendarHandler;
import org.onechurch.events.application.query.listeventregistrations.ListEventRegistrationsHandler;
import org.onechurch.events.application.query.listeventvenues.ListEventVenuesHandler;
import org.onechurch.events.application.query.previeweventoccurrencechange.PreviewEventOccurrenceChangeHandler;
import org.onechurch.events.application.query.previeweventschedule.PreviewEventScheduleHandler;
import org.onechurch.events.domain.repository.EventRepository;
import org.onechurch.events.domain.repository.OccurrenceRepository;
import org.onechurch.events.domain.repository.RegistrationRepository;
import org.onechurch.events.domain.repository.VenueRepository;
import org.onechurch.identityaccess.application.contract.PermissionChecker;
import org.onechurch.people.application.contract.PersonDirectory;
import org.onechurch.shared.application.UnitOfWork;
import org.springframework.context.annotation.Bean;
import org.springframework.context.annotation.Configuration;

import java.time.Clock;
import java.util.List;
@Configuration
public class EventsConfiguration {
    @Bean
    ScheduleReview scheduleReview(VenueReader venues, OccurrenceReader occurrences, Clock clock) {
        return new ScheduleReview(venues, occurrences, clock);
    }
    @Bean
    VenueCatalogue venueCatalogue(VenueRepository venues, EventJournal journal, UnitOfWork tx, Clock clock) {
        return new VenueCatalogue(venues, journal, tx, clock);
    }
    @Bean
    EventPlanning eventPlanning(EventRepository events, VenueRepository venues, OccurrenceRepository occurrences,
        OccurrenceReader occurrenceReader, RegistrationRepository registrations, EventJournal journal,
        EventJournalReader requests, ScheduleReview review, UnitOfWork tx, Clock clock) {
        return new EventPlanning(events, venues, occurrences, occurrenceReader, registrations, journal, requests,
            review, tx, clock);
    }
    @Bean
    OccurrenceChangeReview occurrenceChangeReview(EventReader events, VenueReader venues, OccurrenceReader occurrences,
        RegistrationReader registrations, Clock clock, AttendanceReader attendance,
        List<OccurrenceMovementPolicy> policies) {
        return new OccurrenceChangeReview(events, venues, occurrences, registrations, clock, attendance, policies);
    }
    @Bean
    OccurrenceChanges occurrenceChanges(EventRepository events, VenueRepository venues,
        OccurrenceRepository occurrences, OccurrenceReader reader, RegistrationRepository registrations,
        RegistrationReader registrationReader, EventJournal journal, OccurrenceChangeReview review, UnitOfWork tx,
        Clock clock, List<OccurrenceScheduleListener> listeners) {
        return new OccurrenceChanges(events, venues, occurrences, reader, registrations, registrationReader, journal,
            review, tx, clock, listeners);
    }
    @Bean
    EventRegistrationOperations eventRegistrationOperations(EventReader eventReader, OccurrenceRepository occurrences,
        OccurrenceReader occurrenceReader, RegistrationRepository registrations, RegistrationReader reader,
        PersonDirectory people, EventJournal journal, EventJournalReader requests, UnitOfWork tx, Clock clock,
        AttendanceReader attendance) {
        return new EventRegistrationOperations(eventReader, occurrences, occurrenceReader, registrations, reader,
            people, journal, requests, tx, clock, attendance);
    }
    @Bean
    EventViews eventViews(EventReader events, VenueReader venues, OccurrenceReader occurrences,
        RegistrationReader registrations, PersonDirectory people, EventJournalReader journal) {
        return new EventViews(events, venues, occurrences, registrations, people, journal);
    }
    @Bean
    CancelEventRegistrationHandler cancelEventRegistrationHandler(PermissionChecker auth,
        EventRegistrationOperations registrations) {
        return new CancelEventRegistrationHandler(auth, registrations);
    }
    @Bean
    UpdateEventVenueHandler updateEventVenueHandler(PermissionChecker auth, VenueCatalogue catalogue) {
        return new UpdateEventVenueHandler(auth, catalogue);
    }
    @Bean
    RescheduleEventOccurrencesHandler rescheduleEventOccurrencesHandler(PermissionChecker auth,
        OccurrenceChanges changes) {
        return new RescheduleEventOccurrencesHandler(auth, changes);
    }
    @Bean
    CreateChurchEventHandler createChurchEventHandler(PermissionChecker auth, EventPlanning planning) {
        return new CreateChurchEventHandler(auth, planning);
    }
    @Bean
    RegisterEventPersonHandler registerEventPersonHandler(PermissionChecker auth,
        EventRegistrationOperations registrations) {
        return new RegisterEventPersonHandler(auth, registrations);
    }
    @Bean
    ConfigureEventRegistrationHandler configureEventRegistrationHandler(PermissionChecker auth,
        EventRegistrationOperations registrations) {
        return new ConfigureEventRegistrationHandler(auth, registrations);
    }
    @Bean
    CancelEventOccurrencesHandler cancelEventOccurrencesHandler(PermissionChecker auth, OccurrenceChanges changes) {
        return new CancelEventOccurrencesHandler(auth, changes);
    }
    @Bean
    UpdateChurchEventHandler updateChurchEventHandler(PermissionChecker auth, EventPlanning planning) {
        return new UpdateChurchEventHandler(auth, planning);
    }
    @Bean
    CreateEventSeriesHandler createEventSeriesHandler(PermissionChecker auth, EventPlanning planning) {
        return new CreateEventSeriesHandler(auth, planning);
    }
    @Bean
    CreateEventVenueHandler createEventVenueHandler(PermissionChecker auth, VenueCatalogue catalogue) {
        return new CreateEventVenueHandler(auth, catalogue);
    }
    @Bean
    RestoreEventOccurrenceHandler restoreEventOccurrenceHandler(PermissionChecker auth, OccurrenceChanges changes) {
        return new RestoreEventOccurrenceHandler(auth, changes);
    }
    @Bean
    ListEventCalendarHandler listEventCalendarHandler(PermissionChecker auth, EventViews views) {
        return new ListEventCalendarHandler(auth, views);
    }
    @Bean
    PreviewEventScheduleHandler previewEventScheduleHandler(PermissionChecker auth, ScheduleReview review) {
        return new PreviewEventScheduleHandler(auth, review);
    }
    @Bean
    GetEventOccurrenceHandler getEventOccurrenceHandler(PermissionChecker auth, EventViews views) {
        return new GetEventOccurrenceHandler(auth, views);
    }
    @Bean
    PreviewEventOccurrenceChangeHandler previewEventOccurrenceChangeHandler(PermissionChecker auth,
        OccurrenceChangeReview review) {
        return new PreviewEventOccurrenceChangeHandler(auth, review);
    }
    @Bean
    ListEventVenuesHandler listEventVenuesHandler(PermissionChecker auth, EventViews views) {
        return new ListEventVenuesHandler(auth, views);
    }
    @Bean
    ListEventRegistrationsHandler listEventRegistrationsHandler(PermissionChecker auth, EventViews views) {
        return new ListEventRegistrationsHandler(auth, views);
    }
    @Bean
    GetEventOccurrenceHistoryHandler getEventOccurrenceHistoryHandler(PermissionChecker auth, EventViews views) {
        return new GetEventOccurrenceHistoryHandler(auth, views);
    }
    @Bean
    GetChurchEventHandler getChurchEventHandler(PermissionChecker auth, EventViews views) {
        return new GetChurchEventHandler(auth, views);
    }
    @Bean
    ListChurchEventsHandler listChurchEventsHandler(PermissionChecker auth, EventViews views) {
        return new ListChurchEventsHandler(auth, views);
    }
    @Bean
    GetEventRegistrationHistoryHandler getEventRegistrationHistoryHandler(PermissionChecker auth, EventViews views) {
        return new GetEventRegistrationHistoryHandler(auth, views);
    }
}
